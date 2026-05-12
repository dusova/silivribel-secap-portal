<?php

declare(strict_types=1);

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('SECAP: PHP 7.4 veya üzeri gerekir (şu an ' . PHP_VERSION . ').');
}

header_remove('X-Powered-By');

require_once __DIR__ . '/php7-uyumluluk.php';

define('APP_ROOT', dirname(__DIR__));
define('APP_INCLUDES', __DIR__);
define('APP_STORAGE', APP_ROOT . '/depolama');

$envPath = APP_ROOT . '/ortam.php';
if (!file_exists($envPath)) {
    die('ortam.php bulunamadı. ornek-ortam-ayarlari.php dosyasını ortam.php olarak kopyalayıp düzenleyin.');
}
$env = require $envPath;

require_once APP_INCLUDES . '/ayarlar/uygulama-ayarlari.php';
AppConfig::loadFromEnv($env);

require_once APP_INCLUDES . '/yardimcilar/istek-korumasi.php';
RequestGuard::enforceTrustedHost();

require_once APP_INCLUDES . '/yardimcilar/istemci-ip.php';

define('BASE_PATH', rtrim($env['BASE_PATH'] ?? '', '/'));
define('IS_PRODUCTION', (bool) ($env['PRODUCTION'] ?? false));
define('APP_DEBUG', (bool) ($env['APP_DEBUG'] ?? false));
define('SMTP_ENABLED', (bool) ($env['SMTP_ENABLED'] ?? false));
define('SMTP_HOST', (string) ($env['SMTP_HOST'] ?? ''));
define('SMTP_PORT', (int) ($env['SMTP_PORT'] ?? 587));
define('SMTP_USER', (string) ($env['SMTP_USER'] ?? ''));
define('SMTP_PASS', (string) ($env['SMTP_PASS'] ?? ''));
define('SMTP_SECURE', (string) ($env['SMTP_SECURE'] ?? 'tls'));
define('SMTP_FROM_EMAIL', (string) ($env['SMTP_FROM_EMAIL'] ?? 'secap@silivri.bel.tr'));
define('SMTP_FROM_NAME', (string) ($env['SMTP_FROM_NAME'] ?? 'SECAP Portal'));

if (IS_PRODUCTION || !APP_DEBUG) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

require_once APP_INCLUDES . '/ayarlar/veritabani.php';
require_once APP_INCLUDES . '/ayarlar/kimlik-dogrulama.php';
require_once APP_INCLUDES . '/yardimcilar/csrf.php';
require_once APP_INCLUDES . '/yardimcilar/mesaj.php';
require_once APP_INCLUDES . '/yardimcilar/bildirim-servisi.php';
require_once APP_INCLUDES . '/yardimcilar/denetim-kaydi.php';
require_once APP_INCLUDES . '/yardimcilar/cop-kutusu-servisi.php';
require_once APP_INCLUDES . '/yardimcilar/dogrulayici.php';
require_once APP_INCLUDES . '/yardimcilar/v2-yardimcilari.php';

Database::configure($env);

$bootDebug = (bool) ($env['APP_DEBUG'] ?? false);
try {
    Auth::startSession();
} catch (Throwable $e) {
    error_log('[SECAP][BOOT] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    if ($bootDebug) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><meta charset="utf-8"><title>Başlatma hatası</title>';
        echo '<h1>Başlatma hatası</h1><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
        exit;
    }
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Servis geçici olarak kullanılamıyor. Yönetici sunucu hata günlüğüne bakmalı.');
}
