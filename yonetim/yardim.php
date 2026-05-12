<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
Auth::requireAdmin();

$pageTitle = 'Yardım Merkezi';
$activeNav = 'help';
$helpAudience = 'admin';

require_once APP_ROOT . '/uygulama/parcalar/yardim-merkezi.php';
