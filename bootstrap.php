<?php

declare(strict_types=1);

define('APP_ROOT', __DIR__);

$envPath = APP_ROOT . '/env.php';
if (!file_exists($envPath)) {
    die('env.php bulunamadı. env.example.php dosyasını env.php olarak kopyalayıp düzenleyin.');
}
$env = require $envPath;

define('BASE_PATH', rtrim($env['BASE_PATH'] ?? '', '/'));
define('IS_PRODUCTION', (bool) ($env['PRODUCTION'] ?? false));
define('APP_DEBUG', (bool) ($env['APP_DEBUG'] ?? false));

if (IS_PRODUCTION || !APP_DEBUG) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

require_once APP_ROOT . '/config/Database.php';
require_once APP_ROOT . '/config/Auth.php';
require_once APP_ROOT . '/src/Helpers/Csrf.php';
require_once APP_ROOT . '/src/Helpers/Flash.php';
require_once APP_ROOT . '/src/Helpers/AuditLog.php';
require_once APP_ROOT . '/src/Helpers/Validator.php';

Database::configure($env);

Auth::startSession();
