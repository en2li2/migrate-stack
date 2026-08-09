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
        $pass = ['blocked' => false, 'reason' => null, 'identity' => $empty, 'engine_down' => false];

        $temps = [];

        try {
            $frontPath = $this->localize($frontState, $temps);
            $backPath = $this->localize($backState, $temps);

            // Hiç kimlik yüklenmemişse bu servisin işi yok; zorunluluk
            // denetimi formun kendi required kuralında.
            if ($frontPath === null && $backPath === null) {
                return $pass;
            }

            if ($frontPath === null || $backPath === null) {
                return [
                    'blocked' => true,
                    'reason' => 'Kimliğin her iki yüzü de yüklenmeli.',
                    'identity' => $empty,
                    'engine_down' => false,
                ];
            }

            $identity = $this->readBothSides($frontPath, $backPath);

            $unread = array_keys(array_filter(
                ['TC' => $identity['tc'], 'Ad' => $identity['given'], 'Soyad' => $identity['surname']],
                fn (?string $value): bool => $value === null,
            ));

            // Hiçbir alan okunamadıysa suç görüntüde olmayabilir: motor da kapalı
            // olabilir. Motor kapalıyken kayıt akışını durdurmak yanlış olur —
            // uyarıyla geçilir (kilit yalnız motor ayaktayken anlamlı).
            if (count($unread) === 3 && ! $this->engineAvailable()) {
                return ['blocked' => false, 'reason' => null, 'identity' => $identity, 'engine_down' => true];
            }

            if ($unread !== []) {
                return [
                    'blocked' => true,
                    'reason' => 'Kimlikten okunamadı: '.implode(', ', $unread).'. Daha net/düz bir görüntü yükleyin.',
                    'identity' => $identity,
                    'engine_down' => false,
                ];
            }

            $cardTcDigits = preg_replace('/\D+/', '', $cardTc) ?? '';

            if ($cardTcDigits !== '' && $cardTcDigits !== $identity['tc']) {
                return [
                    'blocked' => true,
                    'reason' => 'TC uyuşmuyor — kimlikte '.$identity['tc'].', kayıtta '.$cardTcDigits.'. Yanlış kişinin kimliği yüklenmiş olabilir.',
                    'identity' => $identity,
                    'engine_down' => false,
                ];
            }

            $readName = trim(($identity['given'] ?? '').' '.($identity['surname'] ?? ''));
            $cardName = trim(trim($cardFirstName).' '.trim($cardLastName));
            $readTokens = $this->ocr->nameTokens($readName);
            $cardTokens = $this->ocr->nameTokens($cardName);

            // Okunan her kelime kartta bulunmalı. Ters yön ARANMAZ: kartta
            // kimlikte olmayan ikinci ad bulunabilir (kimlikte kısaltılmış olabilir).
            if ($readTokens !== [] && $cardTokens !== [] && array_diff($readTokens, $cardTokens) !== []) {
                return [
                    'blocked' => true,
                    'reason' => 'Ad/Soyad uyuşmuyor — kimlikte "'.$readName.'", kayıtta "'.$cardName.'".',
                    'identity' => $identity,
                    'engine_down' => false,
                ];
            }

            return ['blocked' => false, 'reason' => null, 'identity' => $identity, 'engine_down' => false];
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
