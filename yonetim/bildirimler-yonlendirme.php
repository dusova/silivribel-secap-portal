<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
Auth::requireLogin();

$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . BASE_PATH . '/bildirimler' . ($query !== '' ? '?' . $query : ''));
exit;
