<?php

namespace App\Services\Identity;

use Illuminate\Support\Str;

/**
 * Kimlik görüntüsünden TC + SOYAD + AD okuma — YEREL Tesseract (görüntü
 * sunucudan çıkmaz). TC adayları resmî checksum ile doğrulanır; ad/soyad
 * karttaki "SOYADI/SURNAME" ve "ADI/GIVEN NAME(S)" etiketlerinden sökülür
 * (hem yeni TCKK hem eski nüfus cüzdanı düzeni).
 *
 * NOT: Migrate (ara) paneli sürümü — konteynerinde PaddleOCR mikroservisi
 * yoktur; boru hattı yalnız Tesseract + ImageMagick ile çalışır.
 */
class TcKimlikOcrService
{
    /** Boru hattı sürümü — artınca eski önbellek sonuçları geçersizleşir. */
    private const VERSION = 'v9';

    /** Etiket satırından temizlenecek kelimeler. */
    private const LABEL_WORDS = ['SOYADI', 'SOYAD', 'SURNAME', 'ADI', 'AD', 'GIVEN', 'NAME', 'NAMES', 'NAME(S)'];

    /** İçerik hash'i → sonuç (istek içi); kalıcı katman Laravel cache'te. */
    private static array $cache = [];

    /** @return array{tc: ?string, surname: ?string, given: ?string} */
    public function readIdentityFromFile(string $path): array
    {
        $empty = ['tc' => null, 'surname' => null, 'given' => null];

        if (! is_file($path)) {
            return $empty;
        }

        $key = self::VERSION.':'.(md5_file($path) ?: $path);

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        // Aynı görüntü daha önce okunduysa (modal her açılışında) OCR tekrarlanmaz.
        $cached = \Illuminate\Support\Facades\Cache::get('tc-ocr:'.$key);
        if (is_array($cached)) {
            return self::$cache[$key] = $cached;
        }

        $temps = [];

        try {
            // PDF gelirse ilk sayfa görüntüye çevrilir; EXIF dönüklüğü düzeltilir.
            $work = $this->normalizeToImage($path, $temps);

            // Zor fotoğraflar (karanlık zemin, küçük kart, parlama) için
            // iyileştirilmiş varyantlar: gri+normalize+büyütme+keskinleştirme
            // ve dengesiz ışık için adaptif eşikleme.
            $variants = array_values(array_filter([
                $work,
                $enhanced = $this->enhanced($work, $temps),
                // Karanlık zeminde kartı otomatik kırpıp büyütür — küçük kart
                // fotoğraflarında isim satırlarını okunur boyuta getirir.
                $this->cardCropped($work, $temps),
                $enhanced !== null ? $this->thresholded($enhanced, $temps) : null,
            ]));

            $primary = $enhanced ?? $work;
            $result = $empty;
            $text = '';
            $tcAngle = null;
            // Süre bütçesi: TC yoksa ~30sn'de pes; TC varsa isimler için 40sn tavan.
            $start = microtime(true);
            $overBudget = fn (): bool => microtime(true) - $start > ($result['tc'] !== null ? 40.0 : 30.0);
            $rotationCache = [];

            $imageAt = function (string $variant, int $angle) use (&$rotationCache, &$temps): ?string {
                if ($angle === 0) {
                    return $variant;
                }
                $cacheKey = $variant.'@'.$angle;

                return $rotationCache[$cacheKey] ??= $this->rotated($variant, $angle, $temps);
            };

            $scan = function (string $image, string $psm) use (&$result, &$text): void {
                $text .= "\n".$this->ocr($image, $psm);
                $result['tc'] ??= $this->extractValidTc($text);
                [$surname, $given] = $this->extractNames($text);
                $result['surname'] ??= $surname;
                $result['given'] ??= $given;
            };

            $angles = $this->angleOrder($primary);

            // FAZ 1 — hızlı yön/TC keşfi: her yönde ORİJİNAL + iyileştirilmiş
            // varyant, psm 6 (bazı fotoğraflarda iyileştirme TC bölgesini bozar,
            // orijinal daha iyi okur). TC checksum'ı geçen yön doğru yöndür.
            foreach ($angles as $angle) {
                foreach (array_unique([$work, $primary]) as $probe) {
                    if ($overBudget()) {
                        break 2;
                    }

                    $image = $imageAt($probe, $angle);

                    if ($image === null) {
                        continue;
                    }

                    $scan($image, '6');

                    if ($result['tc'] !== null) {
                        $tcAngle = $angle;
                        break 2;
                    }
                }
            }

            // FAZ 2 — derin tarama: TC'nin yönü biliniyorsa yalnız o yönde,
            // bilinmiyorsa tüm yönlerde kalan varyantlar denenir.
            foreach ($tcAngle !== null ? [$tcAngle] : $angles as $angle) {
                foreach ($variants as $variant) {
                    foreach ($variant === $work ? ['6', '11'] : ['6'] as $psm) {
                        if ($overBudget()) {
                            break 3;
                        }

                        if ($psm === '6' && ($variant === $primary || $variant === $work) && $tcAngle === $angle) {
                            continue; // FAZ 1'de bu yönde tarandı
                        }

                        $image = $imageAt($variant, $angle);

                        if ($image === null) {
                            continue;
                        }

                        $scan($image, $psm);

                        if ($result['tc'] !== null && $tcAngle === null) {
                            $tcAngle = $angle; // geç bulunduysa kalan yönler atlanır
                        }

                        if ($result['tc'] !== null && $result['surname'] !== null && $result['given'] !== null) {
                            break 3;
                        }
                    }
                }

                if ($tcAngle !== null && $angle !== $tcAngle) {
                    break;
                }
            }
        } finally {
            foreach ($temps as $temp) {
                @unlink($temp);
            }
        }

        \Illuminate\Support\Facades\Cache::put('tc-ocr:'.$key, $result, now()->addDays(30));

        return self::$cache[$key] = $result;
    }

    /** PDF ise ilk sayfayı PNG'ye çevirir; görüntüyse olduğu gibi döner. */
    private function normalizeToImage(string $path, array &$temps): string
    {
        $isPdf = str_ends_with(strtolower($path), '.pdf')
            || (function_exists('mime_content_type') && mime_content_type($path) === 'application/pdf');

        if (! $isPdf) {
            return $path;
        }

        $target = sys_get_temp_dir().'/tc-ocr-'.uniqid().'.png';
        shell_exec(sprintf('magick -density 200 %s[0] %s 2>/dev/null', escapeshellarg($path), escapeshellarg($target)));

        if (is_file($target)) {
            $temps[] = $target;

            return $target;
        }

        // Telefon fotoğraflarında EXIF dönüklüğü piksellere işlenmemiştir;
        // tesseract EXIF okumaz — burada kalıcı olarak düzeltilir.
        $oriented = sys_get_temp_dir().'/tc-ocr-'.uniqid().'-o.png';
        shell_exec(sprintf('magick %s -auto-orient %s 2>/dev/null', escapeshellarg($path), escapeshellarg($oriented)));

        if (is_file($oriented)) {
            $temps[] = $oriented;

            return $oriented;
        }

        return $path;
    }

    /** Gri ton + kontrast normalize + büyütme + keskinleştirme — karanlık/küçük kart fotoğrafları için. */
    private function enhanced(string $path, array &$temps): ?string
    {
        $target = sys_get_temp_dir().'/tc-ocr-'.uniqid().'-e.png';
        shell_exec(sprintf(
            "magick %s -colorspace Gray -resize '2600x2600<' -normalize -contrast-stretch 1%%x1%% -sharpen 0x1 %s 2>/dev/null",
            escapeshellarg($path),
            escapeshellarg($target),
        ));

        if (! is_file($target)) {
            return null;
        }

        $temps[] = $target;

        return $target;
    }

    /** Karanlık zeminden parlak kartı kırpar (fuzz-trim) ve büyütür. */
    private function cardCropped(string $path, array &$temps): ?string
    {
        $target = sys_get_temp_dir().'/tc-ocr-'.uniqid().'-c.png';
        shell_exec(sprintf(
            "magick %s -colorspace Gray -fuzz 25%% -trim +repage -resize '2600x2600<' -normalize -sharpen 0x1 %s 2>/dev/null",
            escapeshellarg($path),
            escapeshellarg($target),
        ));

        if (! is_file($target)) {
            return null;
        }

        $temps[] = $target;

        return $target;
    }

    /** Adaptif eşikleme — dengesiz ışık/parlamada metni zeminden ayırır. */
    private function thresholded(string $path, array &$temps): ?string
    {
        $target = sys_get_temp_dir().'/tc-ocr-'.uniqid().'-t.png';
        shell_exec(sprintf(
            'magick %s -lat 30x30+10%% %s 2>/dev/null',
            escapeshellarg($path),
            escapeshellarg($target),
        ));

        if (! is_file($target)) {
            return null;
        }

        $temps[] = $target;

        return $target;
    }

    /** Denenecek açı sırası: OSD yön tahmini başa alınır. @return array<int, int> */
    private function angleOrder(string $path): array
    {
        $angles = [0, 90, 270, 180];

        $osd = (string) shell_exec(sprintf('tesseract %s stdout --psm 0 2>/dev/null', escapeshellarg($path)));

        if (preg_match('/Rotate:\s*(\d+)/', $osd, $m)) {
            $suggested = (int) $m[1] % 360;

            if (in_array($suggested, [90, 180, 270], true)) {
                $angles = array_values(array_unique([$suggested, 0, ...$angles]));
            }
        }

        return $angles;
    }

    /** Görüntünün döndürülmüş geçici kopyası. */
    private function rotated(string $path, int $angle, array &$temps): ?string
    {
        $target = sys_get_temp_dir().'/tc-ocr-'.uniqid().'-r'.$angle.'.png';
        shell_exec(sprintf('magick %s -rotate %d %s 2>/dev/null', escapeshellarg($path), $angle, escapeshellarg($target)));

        if (! is_file($target)) {
            return null;
        }

        $temps[] = $target;

        return $target;
    }

    public function readTcFromFile(string $path): ?string
    {
        return $this->readIdentityFromFile($path)['tc'];
    }

    /** OCR metninden checksum'ı geçerli ilk TC'yi çıkarır. */
    public function extractValidTc(string $text): ?string
    {
        $candidates = [];

        if (preg_match_all('/(?<!\d)(\d{11})(?!\d)/', $text, $m)) {
            $candidates = $m[1];
        }

        // OCR araya boşluk/nokta/tire sokabilir — ayraçsız metinde de ara.
        $squashed = preg_replace('/[ .\-]/', '', $text) ?? '';
        if (preg_match_all('/(?<!\d)(\d{11})(?!\d)/', $squashed, $m2)) {
            $candidates = [...$candidates, ...$m2[1]];
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($this->isValidTc($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * SOYADI/SURNAME ve ADI/GIVEN NAME(S) etiketlerinden değerleri söker;
     * değer etiket satırının devamında yoksa bir sonraki satırdan alınır.
     *
     * @return array{0: ?string, 1: ?string} [soyad, ad]
     */
    public function extractNames(string $text): array
    {
        // Önce MRZ (arka yüzdeki makine-okur bölge): SOYAD<<AD<IKINCIAD — varsa
        // en güvenilir kaynak budur, kesin sonuç döner.
        [$mrzSurname, $mrzGiven] = $this->mrzNames($text);
        if ($mrzSurname !== null && $mrzGiven !== null) {
            return [$mrzSurname, $mrzGiven];
        }

        $surname = null;
        $given = null;
        $lines = preg_split('/\R/u', $text) ?: [];

        foreach ($lines as $i => $line) {
            $upper = mb_strtoupper(trim($line), 'UTF-8');

            if ($upper === '') {
                continue;
            }

            if ($surname === null && preg_match('/SOYAD|SURNAME/u', $upper)) {
                $surname = $this->valueFromLabelLine($line) ?? $this->plausibleName($lines[$i + 1] ?? '');

                continue;
            }

            if ($given === null && ! preg_match('/SOYAD|SURNAME/u', $upper) && preg_match('/(^|[^A-ZÇĞİÖŞÜ])AD[Iİ]([^A-ZÇĞİÖŞÜ]|$)|GIVEN/u', $upper)) {
                $given = $this->valueFromLabelLine($line) ?? $this->plausibleName($lines[$i + 1] ?? '');
            }
        }

        return [$surname, $given];
    }

    /**
     * MRZ (ICAO TD1 — kimlik arka yüzü) isim satırı: SOYAD<<AD<IKINCIAD<<<...
     * '<' dolgu karakterleri sayesinde deterministik ayrışır; MRZ alfabesi
     * ASCII olduğundan karşılaştırma ascii-fold token'larla birebir tutar.
     *
     * @return array{0: ?string, 1: ?string} [soyad, ad]
     */
    public function mrzNames(string $text): array
    {
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = mb_strtoupper(str_replace(' ', '', trim($line)), 'UTF-8');
            // OCR diakritik/benzer karakter okumuş olabilir — MRZ alfabesine indir.
            $line = strtr($line, ['Ç' => 'C', 'Ğ' => 'G', 'İ' => 'I', 'Ö' => 'O', 'Ş' => 'S', 'Ü' => 'U', '«' => '<', '(' => '<']);

            if (substr_count($line, '<') < 4 || strlen($line) < 10) {
                continue;
            }

            if (! preg_match('/^([A-Z]{2,})<<([A-Z<]{2,})/', $line, $m)) {
                continue;
            }

            $surname = $m[1];
            $given = trim((string) preg_replace('/<+/', ' ', $m[2]));

            if ($surname !== '' && $given !== '') {
                return [$surname, $given];
            }
        }

        return [null, null];
    }

    /** Resmî TC kimlik no checksum kuralı. */
    public function isValidTc(string $tc): bool
    {
        if (! preg_match('/^[1-9]\d{10}$/', $tc)) {
            return false;
        }

        $d = array_map('intval', str_split($tc));
        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];

        $d10 = (($odd * 7) - $even) % 10;
        if ($d10 < 0) {
            $d10 += 10;
        }

        $d11 = array_sum(array_slice($d, 0, 10)) % 10;

        return $d[9] === $d10 && $d[10] === $d11;
    }

    /** Karşılaştırma için: küçük harf + ascii + yalnız harf token'ları. @return array<int, string> */
    public function nameTokens(?string $value): array
    {
        $normalized = Str::of((string) $value)->lower()->ascii()->replaceMatches('/[^a-z]+/', ' ')->squish()->toString();

        return array_values(array_filter(explode(' ', $normalized), fn (string $t): bool => mb_strlen($t) >= 2));
    }

    /** Etiket satırında etiketten sonra kalan değeri döndürür (yoksa null). */
    private function valueFromLabelLine(string $line): ?string
    {
        // Etiket kelimeleri ve ayraçları at, kalan harf öbeğini dene.
        $value = preg_replace('/SOYADI?|SURNAME|AD[Iİ]|GIVEN\s*NAME\(?S?\)?/iu', ' ', $line) ?? '';

        return $this->plausibleName($value);
    }

    /** Makul isim değeri: kimlikte adlar BÜYÜK harf basılıdır — OCR çöpünü elemek
     *  için ağırlıkla büyük harfli, 1-3 kelimelik, kelime başına ≥2 harf istenir. */
    private function plausibleName(string $value): ?string
    {
        $value = preg_replace('/[^A-Za-zÇĞİÖŞÜçğıöşü ]+/u', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        foreach (self::LABEL_WORDS as $label) {
            if (mb_strtoupper($value, 'UTF-8') === $label) {
                return null;
            }
        }

        // Çöp elekleri: yalnız ≥2 harfli kelimeler kalır; en fazla 3 kelime;
        // harflerin en az %70'i büyük olmalı (kimlik fontu büyük harftir).
        $words = array_values(array_filter(
            explode(' ', $value),
            fn (string $w): bool => mb_strlen($w) >= 2,
        ));

        if ($words === [] || count($words) > 3) {
            return null;
        }

        $value = implode(' ', $words);
        $letters = preg_split('//u', preg_replace('/ /', '', $value) ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $upper = count(array_filter($letters, fn (string $c): bool => mb_strtoupper($c, 'UTF-8') === $c));

        if ($letters === [] || $upper / count($letters) < 0.7) {
            return null;
        }

        $length = mb_strlen($value);

        return $length >= 2 && $length <= 30 ? $value : null;
    }

    private function ocr(string $path, string $psm): string
    {
        $command = sprintf(
            'tesseract %s stdout -l tur --oem 1 --psm %s 2>/dev/null',
            escapeshellarg($path),
            escapeshellarg($psm),
        );

        return (string) shell_exec($command);
    }
}
