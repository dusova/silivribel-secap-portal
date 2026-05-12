<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
Auth::requireLogin();

$pageTitle = 'Yardım Merkezi';
$activeNav = 'help';
$helpAudience = Auth::isAdmin() ? 'admin' : 'department';

require_once APP_ROOT . '/uygulama/parcalar/yardim-merkezi.php';
