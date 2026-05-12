<?php

declare(strict_types=1);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$pageTitle = $pageTitle ?? 'SECAP Portalı';
$activeNav = $activeNav ?? '';
$user = $_SESSION['user'];
$isAdmin = Auth::isAdmin();
$isSuperAdmin = Auth::isSuperAdmin();
$B = BASE_PATH;

$adminNavGroups = [
    'Stratejik Planlama' => [
        ['key' => 'dashboard', 'href' => $B . '/kontrol-merkezi', 'icon' => 'bi-grid-1x2-fill', 'label' => 'Kontrol Merkezi'],
        ['key' => 'actions', 'href' => $B . '/yonetim/eylemler', 'icon' => 'bi-lightning-charge', 'label' => 'SECAP Eylemleri'],
        ['key' => 'help', 'href' => $B . '/yonetim/yardim', 'icon' => 'bi-question-circle-fill', 'label' => 'Kullanım Rehberi'],
    ],
    'Veri Yönetimi' => array_values(array_filter([
        ['key' => 'data_form', 'href' => $B . '/mudurluk/veri-girisi', 'icon' => 'bi-plus-circle', 'label' => 'Veri Girişi'],
        ['key' => 'entries', 'href' => $B . '/yonetim/veri-onay', 'icon' => 'bi-table', 'label' => 'Veri Onayı'],
        ['key' => 'monitoring', 'href' => $B . '/yonetim/veri-izleme', 'icon' => 'bi-graph-up', 'label' => 'Veri İzleme'],
        !$isSuperAdmin ? ['key' => 'notifications', 'href' => $B . '/bildirimler', 'icon' => 'bi-bell', 'label' => 'Bildirimler'] : null,
    ])),
    'Raporlama ve Dışa Aktarım' => [
        ['key' => 'export', 'href' => $B . '/yonetim/disa-aktar', 'icon' => 'bi-download', 'label' => 'Rapor Dışa Aktar'],
    ],
];
if ($isSuperAdmin) {
    $adminNavGroups['Sistem Yönetimi'] = [
        ['key' => 'users',         'href' => $B . '/yonetim/kullanicilar',         'icon' => 'bi-people-fill',  'label' => 'Kullanıcı Yönetimi'],
        ['key' => 'operations',    'href' => $B . '/yonetim/operasyon',    'icon' => 'bi-activity',     'label' => 'Operasyon Merkezi'],
        ['key' => 'notifications', 'href' => $B . '/bildirimler',        'icon' => 'bi-bell-fill',    'label' => 'Bildirimler'],
        ['key' => 'backups',       'href' => $B . '/yonetim/yedekler',       'icon' => 'bi-database-down','label' => 'Sistem Yedekleri'],
        ['key' => 'audit_log',     'href' => $B . '/yonetim/denetim-gunlugu',     'icon' => 'bi-shield-check', 'label' => 'Denetim Günlüğü'],
        ['key' => 'trash',         'href' => $B . '/yonetim/cop-kutusu',         'icon' => 'bi-trash',        'label' => 'Çöp Kutusu'],
        ['key' => 'sessions',      'href' => $B . '/yonetim/oturumlar',      'icon' => 'bi-broadcast',    'label' => 'Aktif Oturumlar'],
    ];
}
$unreadNotifications = 0;
try {
    $pdoForBell = Database::getInstance()->getConnection();
    NotificationService::runDailyChecksForSession($pdoForBell, (int) $user['id']);
    $unreadNotifications = NotificationService::unreadCount($pdoForBell, (int) $user['id']);
} catch (Throwable $e) {
    $unreadNotifications = 0;
}
$deptNav = [
    ['key' => 'dashboard', 'href' => $B . '/kontrol-merkezi', 'icon' => 'bi-grid-1x2-fill', 'label' => 'Kontrol Merkezi'],
    ['key' => 'my_actions', 'href' => $B . '/mudurluk/eylemlerim', 'icon' => 'bi-lightning-charge', 'label' => 'Müdürlük Eylemleri'],
    ['key' => 'my_entries', 'href' => $B . '/mudurluk/veri-gecmisim', 'icon' => 'bi-clock-history', 'label' => 'Veri Girişi Geçmişi'],
    ['key' => 'help', 'href' => $B . '/mudurluk/yardim', 'icon' => 'bi-question-circle-fill', 'label' => 'Kullanım Rehberi'],
];
$initial = mb_strtoupper(mb_substr($user['full_name'], 0, 1));
$deptName = $isSuperAdmin
    ? 'Süper Admin'
    : ($isAdmin ? 'İklim Admin' : ($user['department_name'] ?? 'Müdürlük'));
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — SECAP Portalı
    </title>
    <link rel="stylesheet" href="<?= $B ?>/varliklar/fontlar/fonts.css">
    <link rel="stylesheet" href="<?= $B ?>/varliklar/kutuphaneler/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $B ?>/varliklar/kutuphaneler/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $B ?>/varliklar/stiller/arayuz.css">
    <?php if (!empty($extraHead)): ?>
        <?= $extraHead ?>
        <?php
    endif; ?>
</head>

<body>
    <button class="kenar-daralt-dugme" id="kenarDaraltBtn" aria-label="Menüyü aç/kapat">
        <i class="bi bi-chevron-left"></i>
    </button>
    <div class="uygulama-kapsayici">

        <div id="kenar-cubugu">
            <div class="marka">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= $B ?>/varliklar/belediye-logo.svg" alt="Silivri Belediyesi"
                        style="height: 48px; width: 48px; object-fit: contain; border-radius: 8px;">
                    <div>
                        <div class="marka-alt" style="font-size: 0.55rem; opacity: 0.7;">T.C. SİLİVRİ BELEDİYESİ</div>
                        <div class="marka-baslik" style="font-size: 1.25rem;"><span
                                style="color:var(--renk-vurgu);">SECAP</span> Portalı</div>
                    </div>
                </div>
            </div>

            <nav class="pt-1">
                <?php if ($isAdmin): ?>
                    <?php foreach ($adminNavGroups as $section => $navItems): ?>
                        <div class="gezinme-bolum"><?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php foreach ($navItems as $item): ?>
                        <a href="<?= $item['href'] ?>" class="nav-link <?= $activeNav === $item['key'] ? 'active' : '' ?>"
                            title="<?= $item['label'] ?>">
                            <i class="bi <?= $item['icon'] ?>"></i>
                            <span><?= $item['label'] ?></span>
                            <?php if ($item['key'] === 'notifications' && $unreadNotifications > 0): ?>
                                <span class="badge bg-danger ms-auto" data-notif-badge><?= (int) $unreadNotifications ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="gezinme-bolum">Müdürlük İşlemleri</div>
                    <?php foreach ($deptNav as $item): ?>
                    <a href="<?= $item['href'] ?>" class="nav-link <?= $activeNav === $item['key'] ? 'active' : '' ?>"
                        title="<?= $item['label'] ?>">
                        <i class="bi <?= $item['icon'] ?>"></i>
                        <span><?= $item['label'] ?></span>
                    </a>
                    <?php endforeach; ?>
                    <div class="gezinme-bolum">Hızlı İşlemler</div>
                    <a href="<?= $B ?>/mudurluk/veri-girisi"
                        class="nav-link <?= $activeNav === 'data_form' ? 'active' : '' ?>" title="Yeni Veri Kaydı">
                        <i class="bi bi-plus-circle"></i><span>Yeni Veri Kaydı</span>
                    </a>
                    <a href="<?= $B ?>/bildirimler"
                        class="nav-link <?= $activeNav === 'notifications' ? 'active' : '' ?>" title="Bildirimler">
                        <i class="bi bi-bell"></i><span>Bildirimler</span>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="badge bg-danger ms-auto" data-notif-badge><?= (int) $unreadNotifications ?></span>
                        <?php endif; ?>
                    </a>
                    <?php
                endif; ?>
            </nav>

            <div class="kullanici-kutusu">
                <?php $avatarPath = $B . '/varliklar/avatarlar/' . $user['id'] . '.png'; ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="kullanici-avatar">
                        <?php if (file_exists(APP_ROOT . '/varliklar/avatarlar/' . $user['id'] . '.png')): ?>
                            <img src="<?= $avatarPath ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>
                    <div style="min-width:0;">
                        <div class="text-display"
                            style="font-size:.85rem; font-family:'Outfit',sans-serif; font-weight:700; color:var(--yazi-baslik); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div
                            style="color:var(--yazi-ucuncul); font-size:.7rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                </div>
                <form method="POST" action="<?= $B ?>/cikis">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-sm w-100"
                        style="background:var(--arkaplan-yuzey); color:var(--yazi-ikincil); border:1px solid var(--kenar-acik); font-size:.8rem; font-weight:700;">
                        <i class="bi bi-box-arrow-left me-2"></i>Oturumu Kapat
                    </button>
                </form>
            </div>
        </div>

        <div id="ana-icerik">
            <div class="ust-cubuk">
                <span class="ust-cubuk-baslik">
                    <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <div class="d-flex align-items-center gap-3 ms-auto">
                    <?php if ($isAdmin): ?>
                    <div class="d-none d-lg-flex align-items-center gap-2">
                        <a href="<?= $B ?>/mudurluk/veri-girisi" class="btn btn-sm btn-outline-success" title="Yeni Veri Kaydı">
                            <i class="bi bi-plus-circle"></i><span class="d-none d-xl-inline">Yeni Veri Kaydı</span>
                        </a>
                        <a href="<?= $B ?>/yonetim/disa-aktar" class="btn btn-sm btn-outline-secondary" title="Rapor Dışa Aktar">
                            <i class="bi bi-download"></i><span class="d-none d-xl-inline">Rapor Dışa Aktar</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="dropdown" id="notifBell">
                        <button type="button"
                                class="btn btn-sm btn-link text-reset position-relative p-2"
                                data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                aria-expanded="false" title="Bildirimler">
                            <i class="bi bi-bell-fill fs-5"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                  data-notif-count
                                  style="<?= $unreadNotifications > 0 ? '' : 'display:none;' ?>font-size:.62rem;">
                                <?= (int) $unreadNotifications ?>
                            </span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end shadow" style="width: 360px; max-height: 440px; overflow-y: auto;">
                            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                <strong>Bildirimler</strong>
                                <a href="<?= $B ?>/bildirimler" class="small text-decoration-none">Tümü</a>
                            </div>
                            <div data-notif-list>
                                <div class="text-center text-muted small py-3" data-notif-empty>
                                    <i class="bi bi-inbox me-1"></i>Yeni bildirim yok.
                                </div>
                            </div>
                        </div>
                    </div>
                    <span class="ust-cubuk-tarih">
                        <i class="bi bi-calendar3 me-1"></i>
                        <?= date('d.m.Y') ?>
                    </span>
                </div>
            </div>
            <div class="sayfa-icerik">
                <?= Flash::render() ?>
