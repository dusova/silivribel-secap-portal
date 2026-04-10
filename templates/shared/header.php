<?php
$pageTitle = $pageTitle ?? 'SECAP Portalı';
$activeNav = $activeNav ?? '';
$user = $_SESSION['user'];
$isAdmin = Auth::isAdmin();
$B = BASE_PATH;

$adminNav = [
    ['key' => 'dashboard', 'href' => $B . '/public/dashboard.php', 'icon' => 'bi-grid-1x2-fill', 'label' => 'Kontrol Merkezi'],
    ['key' => 'actions', 'href' => $B . '/public/admin/actions.php', 'icon' => 'bi-lightning-charge', 'label' => 'Eylemler'],
    ['key' => 'entries', 'href' => $B . '/public/admin/entries.php', 'icon' => 'bi-table', 'label' => 'Veri Girişleri'],
    ['key' => 'monitoring', 'href' => $B . '/public/admin/monitoring.php', 'icon' => 'bi-graph-up', 'label' => 'Veri İzleme'],
    ['key' => 'users', 'href' => $B . '/public/admin/users.php', 'icon' => 'bi-people-fill', 'label' => 'Kullanıcılar'],
    ['key' => 'help', 'href' => $B . '/public/admin/help.php', 'icon' => 'bi-question-circle-fill', 'label' => 'Yardım'],
];
$adminQuickNav = [
    ['key' => 'data_form', 'href' => $B . '/public/department/data_form.php', 'icon' => 'bi-plus-circle', 'label' => 'Veri Girişi'],
    ['key' => 'export', 'href' => $B . '/public/admin/export.php', 'icon' => 'bi-download', 'label' => 'Dışa Aktar'],
];
$deptNav = [
    ['key' => 'dashboard', 'href' => $B . '/public/dashboard.php', 'icon' => 'bi-grid-1x2-fill', 'label' => 'Kontrol Merkezi'],
    ['key' => 'my_actions', 'href' => $B . '/public/department/my_actions.php', 'icon' => 'bi-lightning-charge', 'label' => 'Eylemlerim'],
    ['key' => 'my_entries', 'href' => $B . '/public/department/my_entries.php', 'icon' => 'bi-clock-history', 'label' => 'Veri Geçmişim'],
    ['key' => 'help', 'href' => $B . '/public/department/help.php', 'icon' => 'bi-question-circle-fill', 'label' => 'Yardım'],
];
$navItems = $isAdmin ? $adminNav : $deptNav;
$initial = mb_strtoupper(mb_substr($user['full_name'], 0, 1));
$deptName = $user['department_name'] ?? ($isAdmin ? 'Sistem Yöneticisi' : 'Müdürlük');
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — SECAP
    </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Outfit:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $B ?>/assets/css/style.css">
    <?php if (!empty($extraHead)): ?>
        <?= $extraHead ?>
        <?php
    endif; ?>
</head>

<body>
    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="Sidebar aç/kapat">
        <i class="bi bi-chevron-left"></i>
    </button>
    <div class="app-container">

        <div id="sidebar">
            <div class="brand">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= $B ?>/assets/new-logo.svg" alt="Silivri Belediyesi"
                        style="height: 48px; width: 48px; object-fit: contain; border-radius: 8px;">
                    <div>
                        <div class="brand-sub" style="font-size: 0.55rem; opacity: 0.7;">T.C. SİLİVRİ BELEDİYESİ</div>
                        <div class="brand-title" style="font-size: 1.25rem;">SECAP<span
                                style="color:var(--clr-accent);">.</span></div>
                    </div>
                </div>
            </div>

            <nav class="pt-1">
                <div class="nav-section">
                    <?= $isAdmin ? 'Yönetim' : 'Menü' ?>
                </div>
                <?php foreach ($navItems as $item): ?>
                    <a href="<?= $item['href'] ?>" class="nav-link <?= $activeNav === $item['key'] ? 'active' : '' ?>"
                        title="<?= $item['label'] ?>">
                        <i class="bi <?= $item['icon'] ?>"></i>
                        <span><?= $item['label'] ?></span>
                    </a>
                    <?php
                endforeach; ?>

                <?php if ($isAdmin && !empty($adminQuickNav)): ?>
                    <div class="nav-section">Hızlı Erişim</div>
                    <?php foreach ($adminQuickNav as $item): ?>
                        <a href="<?= $item['href'] ?>" class="nav-link <?= $activeNav === $item['key'] ? 'active' : '' ?>"
                            title="<?= $item['label'] ?>">
                            <i class="bi <?= $item['icon'] ?>"></i>
                            <span><?= $item['label'] ?></span>
                        </a>
                        <?php
                    endforeach; ?>
                    <?php
                endif; ?>

                <?php if (!$isAdmin): ?>
                    <div class="nav-section">Hızlı Erişim</div>
                    <a href="<?= $B ?>/public/department/data_form.php"
                        class="nav-link <?= $activeNav === 'data_form' ? 'active' : '' ?>" title="Yeni Veri Gir">
                        <i class="bi bi-plus-circle"></i><span>Yeni Veri Gir</span>
                    </a>
                    <?php
                endif; ?>
            </nav>

            <div class="user-box">
                <?php $avatarPath = $B . '/assets/avatars/' . $user['id'] . '.png'; ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="user-avatar">
                        <?php if (file_exists(APP_ROOT . '/assets/avatars/' . $user['id'] . '.png')): ?>
                            <img src="<?= $avatarPath ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>
                    <div style="min-width:0;">
                        <div class="text-display"
                            style="font-size:.85rem; font-family:'Outfit',sans-serif; font-weight:700; color:var(--text-display); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div
                            style="color:var(--text-tertiary); font-size:.7rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                </div>
                <form method="POST" action="<?= $B ?>/public/logout.php">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-sm w-100"
                        style="background:var(--bg-surface); color:var(--text-secondary); border:1px solid var(--border-light); font-size:.8rem; font-weight:700;">
                        <i class="bi bi-box-arrow-left me-2"></i>Oturumu Kapat
                    </button>
                </form>
            </div>
        </div>

        <div id="main">
            <div class="topbar">
                <span class="topbar-title">
                    <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="topbar-date">
                    <i class="bi bi-calendar3 me-1"></i>
                    <?= date('d.m.Y') ?>
                </span>
            </div>
            <div class="page-body">
                <?= Flash::render() ?>
