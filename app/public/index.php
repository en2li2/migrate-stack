<?php
// Geçici sağlık/doğrulama sayfası — gerçek migrate paneli projesi kurulunca değişecek.
header('Content-Type: text/plain; charset=utf-8');

echo "migrate.wexconnect.com.tr — zemin ayakta\n";
echo "PHP: " . PHP_VERSION . "\n";

$host = getenv('DB_HOST') ?: 'mysql';
$db   = getenv('DB_DATABASE') ?: 'migrate';
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

foreach (['pdo_mysql','mbstring','gd','zip','intl','bcmath','redis','opcache'] as $ext) {
    echo "ext {$ext}: " . (extension_loaded($ext) ? 'OK' : 'YOK') . "\n";
}
