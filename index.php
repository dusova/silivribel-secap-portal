<?php

declare(strict_types=1);

header_remove('X-Powered-By');

$envPath = __DIR__ . '/ortam.php';
if (!is_file($envPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('ortam.php bulunamadı.');
}

$env  = require $envPath;
$base = rtrim((string) ($env['BASE_PATH'] ?? ''), '/');
$path = ($base !== '' ? $base : '') . '/kontrol-merkezi';

header('Location: ' . $path, true, 302);
header('Cache-Control: no-store, no-cache, must-revalidate');
exit;
