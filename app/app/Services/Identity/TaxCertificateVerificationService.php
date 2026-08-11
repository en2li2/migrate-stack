<?php

namespace App\Services\Identity;

use App\Filament\Forms\Components\StructuredAddressFields;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Vergi levhasını müşteri kaydıyla karşılaştırır — YALNIZ KURUMSAL.
 *
 * Kaynak: GİB vergi levhası PDF'inin METİN KATMANI (ünvan + vergi dairesi,
 * OCR'sız kesin) + 400 dpi OCR (VKN, checksum'lı). Görüntü levha ise tümü OCR.
 *
 * Kurallar (Anıl): SADECE kayıtta DOLU alanlar doğrulanır. Felsefe
 * kimlikteki gibi UYAR-AMA-ENGELLEME; TEK sert blok = levhada checksum-geçerli
 * ve KAYITTAN FARKLI VKN (yanlış şirket). Ünvan/vergi dairesi uyuşmazlığı ve
 * okunamama kaydı engellemez, yalnız uyarır.
 */
class TaxCertificateVerificationService
{
    private const DISK = 'local';

    public function __construct(private readonly PaddleOcrClient $paddle) {}

    /**
     * @param  mixed  $certState  Filament FileUpload state (vergi levhası)
     * @return array{blocked:bool, reason:?string, warning:?string, vkn:?string, engine_down:bool}
     */
    public function verifyAgainstCard(mixed $certState, string $cardVkn, string $cardTaxOffice, string $cardTitle): array
    {
        $base = ['blocked' => false, 'reason' => null, 'warning' => null, 'vkn' => null, 'engine_down' => false];
        $temps = [];

        try {
            $path = $this->localize($certState, $temps);
            if ($path === null) {
                return $base; // levha yüklenmemiş — bu servisin işi yok
            }

            $isPdf = strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
            // Metin katmanı (PDF): ünvan + vergi dairesi'ni OCR'sız kesin verir
            // ama VKN TAŞIMAZ. PaddleOCR: VKN dahil her şeyi okur.
            $textLayer = $isPdf ? $this->pdfTextLayer($path) : '';
            // Metin katmanı ünvan+vergi dairesi'ni zaten verir; PaddleOCR'ı YALNIZ
            // metin katmanı yoksa (görüntü levha / metinsiz PDF) çağır (hız).
            $paddleText = trim($textLayer) === '' ? (string) ($this->paddle->text($path) ?? '') : '';
            $text = trim($textLayer."\n".$paddleText);
            // VKN: PaddleOCR GİB levhasındaki sayısal alanı KAÇIRIYOR (test:
            // 200/300/400 dpi hepsinde okumadı) → Tesseract 400dpi+PSM3 fallback.
            $vknRead = $this->pickVkn($paddleText) ?? $this->vknViaTesseract($path, $isPdf, $temps);

            $cardVkn = (string) (preg_replace('/\D+/', '', $cardVkn) ?? '');
            $warnings = [];

            // 1) VKN (dolu) — checksum-geçerli ve FARKLI VKN => SERT BLOK
            if ($cardVkn !== '') {
                if ($vknRead === null) {
                    $warnings[] = 'Vergi levhasından VKN okunamadı — elle doğrulayın.';
                } elseif ($vknRead !== $cardVkn) {
                    return [
                        'blocked' => true,
                        'reason' => 'VKN uyuşmuyor — levhada '.$vknRead.', kayıtta '.$cardVkn.'. Yanlış şirketin levhası yüklenmiş olabilir.',
                        'warning' => null,
                        'vkn' => $vknRead,
                        'engine_down' => false,
                    ];
                }
            }

            $normText = StructuredAddressFields::normalizeName($text);

            // 2) Vergi dairesi (dolu) — metinde geçmeli
            if (trim($cardTaxOffice) !== '') {
                $needle = StructuredAddressFields::normalizeName($cardTaxOffice);
                if ($needle !== '' && $normText !== '' && ! str_contains($normText, $needle)) {
                    $warnings[] = 'Vergi dairesi levhada bulunamadı (kayıt: '.trim($cardTaxOffice).') — elle doğrulayın.';
                }
            }

            // 3) Ünvan (dolu) — belirleyici kelimelerin çoğu levhada geçmeli
            if (trim($cardTitle) !== '' && $normText !== '') {
                $tokens = array_values(array_filter(
                    explode(' ', StructuredAddressFields::normalizeName($cardTitle)),
                    fn (string $t): bool => mb_strlen($t) >= 3,
                ));
                $stop = ['ltd', 'sti', 'tic', 'san', 'ith', 'ihr', 'paz', 'ins', 'taah', 'tek', 'rek', 'ason', 'ltdsti'];
                $sig = array_values(array_diff($tokens, $stop));
                $check = $sig !== [] ? $sig : $tokens;
                if ($check !== []) {
                    $hit = 0;
                    foreach ($check as $t) {
                        if (str_contains($normText, $t)) {
                            $hit++;
                        }
                    }
                    if ($hit / count($check) < 0.5) {
                        $warnings[] = 'Ünvan levha ile yeterince eşleşmedi (kayıt: '.trim($cardTitle).') — elle doğrulayın.';
                    }
                }
            }

            $engineDown = $textLayer === '' && $paddleText === '';

            return [
                'blocked' => false,
                'reason' => null,
                'warning' => $warnings !== [] ? implode(' ', $warnings) : null,
                'vkn' => $vknRead,
                'engine_down' => $engineDown,
            ];
        } finally {
            foreach ($temps as $temp) {
                @unlink($temp);
            }
        }
    }

    /** PDF metin katmanı (gs txtwrite) — GİB levhasında ünvan+vergi dairesi'ni
     *  OCR'sız kesin verir (VKN metin katmanında YOKTUR, PaddleOCR'dan gelir). */
    private function pdfTextLayer(string $path): string
    {
        return (string) shell_exec('gs -q -dNOPAUSE -dBATCH -sDEVICE=txtwrite -sOutputFile=- '.escapeshellarg($path).' 2>/dev/null');
    }

    /** VKN'yi Tesseract ile okur — PaddleOCR GİB levhasındaki VKN alanını
     *  kaçırıyor; Tesseract 400dpi+PSM3 güvenilir okuyor. Tesseract YALNIZ burada. */
    private function vknViaTesseract(string $path, bool $isPdf, array &$temps): ?string
    {
        $combos = $isPdf ? [[400, ['3', '6', '4', '11']], [600, ['3']], [300, ['3']]] : [[0, ['3', '6', '4', '11']]];
        foreach ($combos as [$dpi, $psms]) {
            $img = $dpi > 0 ? $this->renderPdf($path, $dpi, $temps) : $path;
            if ($img === null) {
                continue;
            }
            foreach ($psms as $psm) {
                $out = (string) shell_exec('tesseract '.escapeshellarg($img).' stdout -l tur+eng --psm '.$psm.' 2>/dev/null');
                $v = $this->pickVkn($out);
                if ($v !== null) {
                    return $v;
                }
            }
        }

        return null;
    }

    /** PDF ilk sayfasını PNG'e render eder (gs). */
    private function renderPdf(string $path, int $dpi, array &$temps): ?string
    {
        $target = tempnam(sys_get_temp_dir(), 'vl_').'.png';
        shell_exec(sprintf('gs -q -dNOPAUSE -dBATCH -sDEVICE=png16m -r%d -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s 2>/dev/null', $dpi, escapeshellarg($target), escapeshellarg($path)));
        if (! is_file($target)) {
            return null;
        }
        $temps[] = $target;

        return $target;
    }

    /** Metinden checksum-geçerli ilk 10 haneli VKN. */
    private function pickVkn(string $text): ?string
    {
        if ($text === '') {
            return null;
        }
        if (preg_match_all('/(?<!\d)\d{10}(?!\d)/', $text, $m)) {
            foreach ($m[0] as $cand) {
                if ($this->validVkn($cand)) {
                    return $cand;
                }
            }
        }

        return null;
    }

    /** Türk VKN (10 hane) checksum. */
    private function validVkn(string $v): bool
    {
        if (! preg_match('/^[0-9]{10}$/', $v)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $t = ((int) $v[$i] + (9 - $i)) % 10;
            if ($t != 0) {
                $t = ($t * (2 ** (9 - $i))) % 9;
                if ($t == 0) {
                    $t = 9;
                }
            }
            $sum += $t;
        }

        return ((10 - ($sum % 10)) % 10) == (int) $v[9];
    }

    /** Filament FileUpload state'inden yerel okunabilir dosya yolu (çağıran temizler). */
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

        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($state)) {
            return null;
        }

        $extension = pathinfo($state, PATHINFO_EXTENSION) ?: 'pdf';
        $temp = tempnam(sys_get_temp_dir(), 'vlc_');
        if ($temp === false) {
            return null;
        }
        $localTarget = $temp.'.'.$extension;
        @unlink($temp);

        $stream = $disk->readStream($state);
        if ($stream === null || $stream === false) {
            return null;
        }
        $out = fopen($localTarget, 'wb');
        if ($out === false) {
            fclose($stream);

            return null;
        }
        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);
        $temps[] = $localTarget;

        return is_file($localTarget) ? $localTarget : null;
    }
}