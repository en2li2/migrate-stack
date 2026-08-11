<?php

namespace App\Services\Identity;

use Illuminate\Support\Str;

/**
 * Kimlik görüntüsünden TC + SOYAD + AD okuma — YEREL PaddleOCR (migrate-ocr,
 * iç ağ). Görüntü sunucudan çıkmaz. TC adayları resmî checksum ile doğrulanır;
 * ad/soyad öncelikle MRZ'den (arka yüz makine-okur bölge), yoksa karttaki
 * SOYADI/SURNAME + ADI/GIVEN etiketlerinden sökülür.
 *
 * NOT: Tesseract boru hattı KALDIRILDI (2026-08-11) — PaddleOCR hem hızlı
 * (~3sn) hem doğru; okuma yalnız PaddleOCR üzerinden yapılır. Servis
 * erişilemezse boş sonuç döner (çağıran "okunamadı" uyarısı gösterir).
 */
class TcKimlikOcrService
{
    /** Boru hattı sürümü — artınca eski önbellek sonuçları geçersizleşir. */
    private const VERSION = 'v11';

    /** Etiket satırından temizlenecek kelimeler. */
    private const LABEL_WORDS = ['SOYADI', 'SOYAD', 'SURNAME', 'ADI', 'AD', 'GIVEN', 'NAME', 'NAMES', 'NAME(S)'];

    /** İçerik hash'i → sonuç (istek içi); kalıcı katman Laravel cache'te. */
    private static array $cache = [];

    private readonly PaddleOcrClient $paddle;

    public function __construct(?PaddleOcrClient $paddle = null)
    {
        $this->paddle = $paddle ?? new PaddleOcrClient();
    }

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

        $cached = \Illuminate\Support\Facades\Cache::get('tc-ocr:'.$key);
        if (is_array($cached)) {
            return self::$cache[$key] = $cached;
        }

        $text = $this->paddle->text($path);

        // PaddleOCR erişilemez/boş → okunamadı. ÖNBELLEĞE ALINMAZ ki servis
        // geçici kapalıysa sonraki denemede tekrar okunsun.
        if ($text === null || trim($text) === '') {
            return self::$cache[$key] = $empty;
        }

        [$surname, $given] = $this->extractNames($text);
        $result = [
            'tc' => $this->extractValidTc($text),
            'surname' => $surname,
            'given' => $given,
        ];

        \Illuminate\Support\Facades\Cache::put('tc-ocr:'.$key, $result, now()->addDays(30));

        return self::$cache[$key] = $result;
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
}
