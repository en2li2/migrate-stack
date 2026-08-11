<?php

namespace App\Services\Identity;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Yüklenen kimlik görüntüsünü müşteri kartıyla karşılaştırır.
 *
 * Amaç YANLIŞ KİMLİK yüklenmesini engellemek: operatör A müşterisinin kaydına
 * B'nin kimliğini yüklerse kayıt ilerlemez. Kart bilgisi (TC/ad/soyad) migrate
 * panelinde müşteri kaydında zaten bulunduğundan karşılaştırma doğrudan yapılır.
 *
 * Okuma boru hattı [[TcKimlikOcrService]]: arka yüz MRZ kesin kabul edilir,
 * TC checksum doğru yönü seçer. Görüntü sunucudan çıkmaz (OCR yerelde).
 */
class IdentityVerificationService
{
    /** Evrakların durduğu disk (Evrak modalındaki FileUpload ile aynı). */
    private const DISK = 'local';

    public function __construct(private readonly TcKimlikOcrService $ocr) {}

    /**
     * @param  mixed  $frontState  Filament FileUpload state (ön yüz)
     * @param  mixed  $backState   Filament FileUpload state (arka yüz)
     * @return array{blocked: bool, reason: ?string, identity: array{tc: ?string, surname: ?string, given: ?string}, engine_down: bool}
     */
    public function verifyAgainstCard(
        mixed $frontState,
        mixed $backState,
        string $cardTc,
        string $cardFirstName,
        string $cardLastName,
    ): array {
        $empty = ['tc' => null, 'surname' => null, 'given' => null];
        $pass = ['blocked' => false, 'reason' => null, 'warning' => null, 'identity' => $empty, 'engine_down' => false];

        $temps = [];

        try {
            $frontPath = $this->localize($frontState, $temps);
            $backPath = $this->localize($backState, $temps);

            // Hiç kimlik yüklenmemişse bu servisin işi yok; zorunluluk
            // denetimi formun kendi required kuralında.
            if ($frontPath === null && $backPath === null) {
                return $pass;
            }

            // Migrate (temizlik) paneli: OCR yalnız Tesseract → İSİM okumaları
            // güvenilmez (ör. "Fırat" -> "Nevin"). TEK SERT BLOK = OCR'ın
            // checksum-geçerli ve KARTTAN FARKLI bir TC okuması (yanlış kişi
            // koruması). Tek yüz / okunamama / isim uyuşmazlığı kaydı ENGELLEMEZ;
            // yalnız UYARI olarak döner (operatör migrate'te elle doğrular).
            $identity = ['tc' => null, 'surname' => null, 'given' => null];
            if ($frontPath !== null) {
                $identity = $this->ocr->readIdentityFromFile($frontPath);
            }
            if ($backPath !== null) {
                $back = $this->ocr->readIdentityFromFile($backPath);
                $identity['tc'] ??= $back['tc'];
                if ($back['surname'] !== null && $back['given'] !== null) {
                    $identity['surname'] = $back['surname'];
                    $identity['given'] = $back['given'];
                } else {
                    $identity['surname'] ??= $back['surname'];
                    $identity['given'] ??= $back['given'];
                }
            }

            $cardTcDigits = preg_replace('/\D+/', '', $cardTc) ?? '';
            $readTc = $identity['tc'];

            // SERT BLOK: net (checksum-geçerli) ve karttan farklı TC → yanlış kişi.
            if ($cardTcDigits !== '' && $readTc !== null && $this->ocr->isValidTc($readTc) && $readTc !== $cardTcDigits) {
                return [
                    'blocked' => true,
                    'reason' => 'TC uyuşmuyor — kimlikte '.$readTc.', kayıtta '.$cardTcDigits.'. Yanlış kişinin kimliği yüklenmiş olabilir.',
                    'warning' => null,
                    'identity' => $identity,
                    'engine_down' => false,
                ];
            }

            // Bundan sonrası UYARI (kaydı engellemez).
            $warnings = [];

            if ($frontPath === null || $backPath === null) {
                $warnings[] = 'Kimliğin yalnız bir yüzü yüklendi.';
            }

            $unread = array_keys(array_filter(
                ['TC' => $identity['tc'], 'Ad' => $identity['given'], 'Soyad' => $identity['surname']],
                fn (?string $value): bool => $value === null,
            ));

            $engineDown = count($unread) === 3 && ! $this->engineAvailable();

            if (! $engineDown) {
                if ($unread !== []) {
                    $warnings[] = 'OCR okuyamadı: '.implode(', ', $unread).' — elle doğrulayın.';
                } else {
                    $readName = trim(($identity['given'] ?? '').' '.($identity['surname'] ?? ''));
                    $cardName = trim(trim($cardFirstName).' '.trim($cardLastName));
                    $readTokens = $this->ocr->nameTokens($readName);
                    $cardTokens = $this->ocr->nameTokens($cardName);
                    if ($readTokens !== [] && $cardTokens !== [] && array_diff($readTokens, $cardTokens) !== []) {
                        $warnings[] = 'Ad/Soyad OCR ile uyuşmadı (kimlikte "'.$readName.'", kayıtta "'.$cardName.'") — OCR hatası olabilir, elle doğrulayın.';
                    }
                }
            }

            return [
                'blocked' => false,
                'reason' => null,
                'warning' => $warnings !== [] ? implode(' ', $warnings) : null,
                'identity' => $identity,
                'engine_down' => $engineDown,
            ];
        } finally {
            foreach ($temps as $temp) {
                @unlink($temp);
            }
        }
    }

    /**
     * Ön + arka yüzü birleştirir: arka yüz MRZ'si isimlerde KESİNDİR ve ön
     * yüzün tahminini ezer.
     *
     * @return array{tc: ?string, surname: ?string, given: ?string}
     */
    private function readBothSides(string $frontPath, string $backPath): array
    {
        $identity = $this->ocr->readIdentityFromFile($frontPath);

        // HIZ: ön yüz TC + ad + soyad'ı TAM verdiyse arka yüzü OKUMA (2 kat hızlı).
        // Arka yüz yalnız ön eksikse ya da isim MRZ'den doğrulanacaksa gerekir.
        if ($identity['tc'] !== null && $identity['surname'] !== null && $identity['given'] !== null) {
            return $identity;
        }

        $back = $this->ocr->readIdentityFromFile($backPath);

        $identity['tc'] ??= $back['tc'];

        if ($back['surname'] !== null && $back['given'] !== null) {
            $identity['surname'] = $back['surname'];
            $identity['given'] = $back['given'];
        } else {
            $identity['surname'] ??= $back['surname'];
            $identity['given'] ??= $back['given'];
        }

        return $identity;
    }

    /** Okuma motoru ayakta mı: yerel tesseract var mı. */
    private function engineAvailable(): bool
    {
        return trim((string) shell_exec('command -v tesseract 2>/dev/null')) !== '';
    }

    /**
     * Filament FileUpload state'inden yerel okunabilir dosya yolu üretir.
     * Çağıran temizler.
     *
     * DİKKAT: state TEK bir TemporaryUploadedFile NESNESİ olabilir — (array)
     * cast'i nesnenin içini döker ve yolu kaybettirir.
     */
    private function localize(mixed $state, array &$temps): ?string
    {
        if ($state instanceof TemporaryUploadedFile) {
            $path = $state->getRealPath();

            return is_string($path) && is_file($path) ? $path : null;
        }

        if (is_array($state)) {
            foreach ($state as $item) {
                $resolved = $this->localize($item, $temps);

                if ($resolved !== null) {
                    return $resolved;
                }
            }

            return null;
        }

        if (! is_string($state) || $state === '') {
            return null;
        }

        // Yüklenmiş dosya: diskten geçici dosyaya indirilir.
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($state)) {
            return null;
        }

        $extension = pathinfo($state, PATHINFO_EXTENSION) ?: 'jpg';
        $temp = tempnam(sys_get_temp_dir(), 'idv_');

        if ($temp === false) {
            return null;
        }

        // OCR uzantıya bakarak PDF/HEIC ayrımı yapıyor — uzantı korunmalı.
        $target = $temp.'.'.$extension;
        @unlink($temp);

        $stream = $disk->readStream($state);

        if ($stream === null || $stream === false) {
            return null;
        }

        $out = fopen($target, 'wb');

        if ($out === false) {
            fclose($stream);

            return null;
        }

        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);

        $temps[] = $target;

        return is_file($target) ? $target : null;
    }
}
