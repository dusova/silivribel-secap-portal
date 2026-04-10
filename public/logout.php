<?php
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/public/dashboard.php');
    exit;
}

Csrf::check();
Auth::logout();

header('Location: ' . BASE_PATH . '/public/login.php');
exit;
