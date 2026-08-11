<?php

namespace App\Services\Identity;

use Illuminate\Support\Facades\Http;

/**
 * Yerel PaddleOCR mikroservisi (migrate-ocr, iç ağ ocr:8000) istemcisi.
 * Görüntüyü/PDF'i gönderir, tespit edilen tüm metni satır satır döndürür.
 * Servis kapalı/erişilemez/boşsa null döner — çağıran buna göre davranır.
 * Görüntü sunucudan çıkmaz (iç docker ağı, dışarı port yok).
 */
class PaddleOcrClient
{
    public function text(string $path): ?string
    {
        $url = rtrim((string) env('MIGRATE_OCR_URL', 'http://ocr:8000'), '/');
        if ($url === '' || ! is_file($path)) {
            return null;
        }

        $temps = [];

        try {
            // PDF ise ilk sayfayı görüntüye çevir (PaddleOCR görüntü bekler).
            $img = $path;
            if (str_ends_with(strtolower($path), '.pdf')) {
                $img = sys_get_temp_dir().'/paddle-'.uniqid().'.png';
                shell_exec(sprintf('magick -density 200 %s[0] %s 2>/dev/null', escapeshellarg($path), escapeshellarg($img)));
                if (! is_file($img)) {
                    return null;
                }
                $temps[] = $img;
            }

            $bytes = @file_get_contents($img);
            if ($bytes === false) {
                return null;
            }

            $response = Http::timeout(25)
                ->attach('file', $bytes, 'image.png')
                ->post($url.'/ocr');

            if (! $response->successful()) {
                return null;
            }

            $text = (string) ($response->json('text') ?? '');

            return trim($text) === '' ? null : $text;
        } catch (\Throwable $e) {
            return null;
        } finally {
            foreach ($temps as $t) {
                @unlink($t);
            }
        }
    }
}
