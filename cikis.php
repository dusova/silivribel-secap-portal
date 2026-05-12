<?php
require_once __DIR__ . '/uygulama/baslat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/kontrol-merkezi');
    exit;
}

Csrf::check();
Auth::logout();

header('Location: ' . BASE_PATH . '/giris?logged_out=1');
exit;
