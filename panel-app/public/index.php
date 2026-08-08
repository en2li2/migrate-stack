<?php
// panel.wexconnect.com.tr — geçici zemin (ISP Core panel uygulaması buraya gelecek).
header('Content-Type: text/plain; charset=utf-8');

echo "panel.wexconnect.com.tr — zemin ayakta\n";
echo "PHP: " . PHP_VERSION . "\n";

$host = getenv('DB_HOST') ?: 'mysql';
$db   = getenv('DB_DATABASE') ?: 'isp_panel';
$user = getenv('DB_USERNAME') ?: 'migrate';
$pass = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "MySQL: BAGLANDI (server {$ver}, db={$db})\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "MySQL: BAGLANAMADI — " . $e->getMessage() . "\n";
}
