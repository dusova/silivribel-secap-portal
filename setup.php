<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu komut yalnızca CLI üzerinden çalıştırılabilir.');
}

$envPath = __DIR__ . '/env.php';
if (!file_exists($envPath)) {
    exit("env.php bulunamadı.\n");
}

$env = require $envPath;
$setupEnabled = (bool) ($env['SETUP_ENABLED'] ?? false);
$setupSecret = (string) ($env['SETUP_SECRET'] ?? '');

if (!$setupEnabled || $setupSecret === '') {
    exit("Kurulum devre dışı. env.php içinde SETUP_ENABLED=true ve SETUP_SECRET tanımlayın.\n");
}

echo "UYARI: Bu işlem veritabanını sıfırlar ve örnek kullanıcı şifrelerini yeniden üretir.\n";
echo "Devam etmek için kurulum secret'ını girin: ";
$input = trim((string) fgets(STDIN));
if (!hash_equals($setupSecret, $input)) {
    exit("Geçersiz secret.\n");
}

$dbHost = (string) ($env['DB_SETUP_HOST'] ?? $env['DB_HOST'] ?? 'localhost');
$dbPort = (string) ($env['DB_SETUP_PORT'] ?? $env['DB_PORT'] ?? '3306');
$dbUser = (string) ($env['DB_SETUP_USER'] ?? $env['DB_USER'] ?? '');
$dbPass = (string) ($env['DB_SETUP_PASS'] ?? $env['DB_PASS'] ?? '');
$dbName = (string) ($env['DB_NAME'] ?? 'secap_portal');
$charset = (string) ($env['DB_CHARSET'] ?? 'utf8mb4');

$schemaPath = __DIR__ . '/schema.sql';
if (!file_exists($schemaPath)) {
    exit("schema.sql bulunamadı.\n");
}

if ($dbUser === '') {
    exit("Kurulum için DB_SETUP_USER veya DB_USER tanımlanmalıdır.\n");
}

try {
    $pdoAdmin = new PDO(
        "mysql:host={$dbHost};port={$dbPort};charset={$charset}",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = (string) file_get_contents($schemaPath);
    $sql = str_replace('secap_portal', $dbName, $sql);
    $pdoAdmin->exec($sql);
    echo "Veritabanı şeması başarıyla uygulandı.\n";
} catch (Throwable $e) {
    exit("Schema uygulanamadı: {$e->getMessage()}\n");
}

require_once __DIR__ . '/config/Database.php';
Database::configure($env);
$pdo = Database::getInstance()->getConnection();

$users = [
    'admin',
    'iklim_user',
    'fen_user',
    'park_user',
    'temizlik_user',
    'ulasim_user',
    'basin_user',
    'destek_user',
    'imar_user',
    'zabita_user',
    'mali_user',
];

$passwords = [];
$stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE username = :username');
foreach ($users as $username) {
    $plainPassword = bin2hex(random_bytes(8)) . '!';
    $stmt->execute([
        ':hash' => password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]),
        ':username' => $username,
    ]);
    $passwords[$username] = $plainPassword;
}

echo "İlk kullanıcı şifreleri üretildi. Bunları güvenli bir yerde saklayın.\n\n";
foreach ($passwords as $username => $plainPassword) {
    echo str_pad($username, 18) . " : {$plainPassword}\n";
}

$counts = [
    'departments' => (int) $pdo->query('SELECT COUNT(*) FROM departments')->fetchColumn(),
    'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'actions' => (int) $pdo->query('SELECT COUNT(*) FROM actions')->fetchColumn(),
    'activities' => (int) $pdo->query('SELECT COUNT(*) FROM activities')->fetchColumn(),
    'kpis' => (int) $pdo->query('SELECT COUNT(*) FROM kpis')->fetchColumn(),
];

echo "\nKurulum özeti:\n";
echo "  Müdürlük : {$counts['departments']}\n";
echo "  Kullanıcı: {$counts['users']}\n";
echo "  Eylem    : {$counts['actions']}\n";
echo "  Faaliyet : {$counts['activities']}\n";
echo "  KPI      : {$counts['kpis']}\n";
