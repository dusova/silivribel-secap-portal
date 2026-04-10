<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$pdo       = Database::getInstance()->getConnection();
$isAdmin   = Auth::isAdmin();
$deptId    = Auth::getDepartmentId();
$pageTitle = 'Kontrol Merkezi';
$activeNav = 'dashboard';

if ($isAdmin) {
    $stats = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM actions  WHERE status != 'cancelled')       AS total_actions,
            (SELECT COUNT(*) FROM activities WHERE is_active = 1)             AS total_activities,
            (SELECT COUNT(*) FROM kpis     WHERE is_active = 1)               AS total_kpis,
            (SELECT COUNT(*) FROM data_entries)                               AS total_entries,
            (SELECT COUNT(*) FROM data_entries WHERE is_verified = 0)         AS pending_verify,
            (SELECT COUNT(*) FROM departments WHERE is_active = 1)            AS total_depts,
            (SELECT COUNT(*) FROM users WHERE is_active = 1)                  AS total_users"
    )->fetch();

    $deptProgress = $pdo->query(
        "SELECT d.name AS dept_name, d.slug,
                COUNT(DISTINCT a.id)  AS total_actions,
                COUNT(DISTINCT k.id)  AS total_kpis,
                COUNT(de.id)          AS total_entries,
                SUM(CASE WHEN de.year = YEAR(NOW()) THEN 1 ELSE 0 END) AS entries_this_year
         FROM   departments d
         LEFT JOIN actions    a  ON a.responsible_department_id = d.id AND a.status != 'cancelled'
         LEFT JOIN kpis       k  ON k.action_id = a.id AND k.is_active = 1
         LEFT JOIN data_entries de ON de.department_id = d.id
         WHERE  d.is_active = 1 AND d.slug NOT LIKE '%-dis-paydas'
         GROUP  BY d.id
         ORDER  BY total_entries DESC"
    )->fetchAll();

    $recentEntries = $pdo->query(
        "SELECT de.year, de.value, de.is_verified, de.created_at,
                k.name AS kpi_name, k.unit,
                a.code AS action_code,
                d.name AS dept_name,
                u.full_name AS user_name
         FROM   data_entries de
         JOIN   kpis        k  ON k.id  = de.kpi_id
         JOIN   actions     a  ON a.id  = de.action_id
         JOIN   departments d  ON d.id  = de.department_id
         JOIN   users       u  ON u.id  = de.entered_by
         ORDER  BY de.created_at DESC
         LIMIT  10"
    )->fetchAll();

    $statusDist = $pdo->query(
        "SELECT status, COUNT(*) AS cnt FROM actions WHERE status != 'cancelled' GROUP BY status"
    )->fetchAll();

    $catDist = $pdo->query(
        "SELECT COALESCE(category, 'Diğer') AS category, COUNT(*) AS cnt
         FROM actions WHERE status != 'cancelled'
         GROUP BY category ORDER BY cnt DESC LIMIT 8"
    )->fetchAll();

} else {
    $accessibleActionIds = Auth::getAccessibleActionIds($pdo);

    $stats = [
        'total_actions' => count($accessibleActionIds),
        'total_kpis' => 0,
        'total_entries' => 0,
        'entries_this_year' => 0,
        'pending_verify' => 0,
    ];

    if (!empty($accessibleActionIds)) {
        $inClause = Auth::getSqlPlaceholders($accessibleActionIds);

        $kpiStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM kpis WHERE action_id IN ({$inClause}) AND is_active = 1"
        );
        $kpiStmt->execute($accessibleActionIds);
        $stats['total_kpis'] = (int) $kpiStmt->fetchColumn();

        $actionProgress = $pdo->prepare(
            "SELECT a.code, a.title, a.status, a.end_year,
                    COUNT(DISTINCT k.id)  AS total_kpis,
                    COUNT(DISTINCT de.id) AS entries_this_year
             FROM   actions a
             LEFT JOIN kpis k  ON k.action_id = a.id AND k.is_active = 1
             LEFT JOIN data_entries de
                    ON de.action_id = a.id
                   AND de.department_id = ?
                   AND de.year = YEAR(NOW())
             WHERE  a.id IN ({$inClause})
             GROUP  BY a.id
             ORDER  BY a.code"
        );
        $actionProgress->execute(array_merge([$deptId], $accessibleActionIds));
        $actionProgress = $actionProgress->fetchAll();
    } else {
        $actionProgress = [];
    }

    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_entries,
            SUM(CASE WHEN year = YEAR(NOW()) THEN 1 ELSE 0 END) AS entries_this_year,
            SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) AS pending_verify
         FROM data_entries
         WHERE department_id = :dept_id"
    );
    $stmt->execute([':dept_id' => $deptId]);
    $entryStats = $stmt->fetch() ?: [];
    $stats['total_entries'] = (int) ($entryStats['total_entries'] ?? 0);
    $stats['entries_this_year'] = (int) ($entryStats['entries_this_year'] ?? 0);
    $stats['pending_verify'] = (int) ($entryStats['pending_verify'] ?? 0);

    $recentEntries = $pdo->prepare(
        "SELECT de.year, de.value, de.is_verified, de.created_at,
                k.name AS kpi_name, k.unit,
                a.code AS action_code
         FROM   data_entries de
         JOIN   kpis    k ON k.id = de.kpi_id
         JOIN   actions a ON a.id = de.action_id
         WHERE  de.department_id = :dept_id
         ORDER  BY de.created_at DESC
         LIMIT  5"
    );
    $recentEntries->execute([':dept_id' => $deptId]);
    $recentEntries = $recentEntries->fetchAll();
}

$statusLabels = [
    'planned'   => ['label' => 'Planlandı',   'cls' => 'badge-planned'],
    'ongoing'   => ['label' => 'Devam Ediyor', 'cls' => 'badge-ongoing'],
    'completed' => ['label' => 'Tamamlandı',  'cls' => 'badge-completed'],
    'cancelled' => ['label' => 'İptal',        'cls' => 'badge-cancelled'],
];

require_once APP_ROOT . '/templates/shared/header.php';
?>

<div class="row g-3 mb-4">
    <?php if ($isAdmin): ?>
    <?php
    $statCards = [
        ['label' => 'Toplam Eylem',    'value' => $stats['total_actions'],  'icon' => 'bi-lightning-charge-fill', 'color' => '#E8F5E9', 'icolor' => '#2E7D32'],
        ['label' => 'Faaliyet',        'value' => $stats['total_activities'],'icon' => 'bi-activity',             'color' => '#E3F2FD', 'icolor' => '#1565C0'],
        ['label' => 'Toplam KPI',      'value' => $stats['total_kpis'],     'icon' => 'bi-bar-chart-fill',        'color' => '#EDE7F6', 'icolor' => '#4527A0'],
        ['label' => 'Veri Girişi',     'value' => $stats['total_entries'],  'icon' => 'bi-database-fill',         'color' => '#FFF8E1', 'icolor' => '#FF8F00'],
        ['label' => 'Onay Bekleyen',   'value' => $stats['pending_verify'], 'icon' => 'bi-hourglass-split',       'color' => '#FCE4EC', 'icolor' => '#C2185B'],
        ['label' => 'Kullanıcı',       'value' => $stats['total_users'],    'icon' => 'bi-people-fill',           'color' => '#E0F7FA', 'icolor' => '#00838F'],
    ];
    ?>
    <?php foreach ($statCards as $sc): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100 p-3">
            <div class="s-icon mb-2" style="background:<?= $sc['color'] ?>; color:<?= $sc['icolor'] ?>;">
                <i class="bi <?= $sc['icon'] ?>"></i>
            </div>
            <div class="s-value"><?= number_format((int)$sc['value']) ?></div>
            <div class="s-label"><?= $sc['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <?php
    $statCards = [
        ['label' => 'Eylemim',         'value' => $stats['total_actions'],    'icon' => 'bi-lightning-charge-fill', 'color' => '#E8F5E9', 'icolor' => '#2E7D32'],
        ['label' => 'KPI\'larım',      'value' => $stats['total_kpis'],       'icon' => 'bi-bar-chart-fill',        'color' => '#E3F2FD', 'icolor' => '#1565C0'],
        ['label' => 'Toplam Giriş',    'value' => $stats['total_entries'],    'icon' => 'bi-database-fill',         'color' => '#FFF8E1', 'icolor' => '#FF8F00'],
        ['label' => 'Bu Yıl Giriş',   'value' => $stats['entries_this_year'],'icon' => 'bi-calendar-check',        'color' => '#F3E5F5', 'icolor' => '#6A1B9A'],
    ];
    ?>
    <?php foreach ($statCards as $sc): ?>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100 p-3">
            <div class="s-icon mb-2" style="background:<?= $sc['color'] ?>; color:<?= $sc['icolor'] ?>;">
                <i class="bi <?= $sc['icon'] ?>"></i>
            </div>
            <div class="s-value"><?= number_format((int)$sc['value']) ?></div>
            <div class="s-label"><?= $sc['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="row g-3">

    <div class="col-12 col-xl-7">

        <?php if ($isAdmin): ?>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-pie-chart me-2 text-success"></i>Eylem Durumları
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height:220px;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-tag me-2 text-primary"></i>Kategori Dağılımı
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height:220px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-building me-2 text-success"></i>Müdürlük Bazlı İlerleme</span>
                <a href="<?= BASE_PATH ?>/public/admin/monitoring.php" class="btn btn-sm btn-outline-success">Detaylı İzleme</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Müdürlük</th>
                                <th class="text-center">Eylem</th>
                                <th class="text-center">KPI</th>
                                <th class="text-center">Bu Yıl</th>
                                <th>Doluluk</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($deptProgress as $dp): ?>
                            <?php
                                $pct = $dp['total_kpis'] > 0
                                    ? min(100, (int)(($dp['entries_this_year'] / $dp['total_kpis']) * 100))
                                    : 0;
                                $barCls = $pct >= 80 ? 'bg-success' : ($pct >= 40 ? 'bg-warning' : 'bg-danger');
                            ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($dp['dept_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-center"><?= (int)$dp['total_actions'] ?></td>
                                <td class="text-center"><?= (int)$dp['total_kpis'] ?></td>
                                <td class="text-center"><?= (int)$dp['entries_this_year'] ?></td>
                                <td style="min-width:120px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1">
                                            <div class="progress-bar <?= $barCls ?>" style="width:<?= $pct ?>%"></div>
                                        </div>
                                        <small class="text-muted" style="font-size:.75rem; width:32px;"><?= $pct ?>%</small>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-lightning-charge me-2 text-success"></i>Eylem İlerleme Durumu (<?= date('Y') ?>)</span>
                <a href="<?= BASE_PATH ?>/public/department/my_actions.php" class="btn btn-sm btn-outline-success">Tümünü Gör</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kod</th>
                                <th>Eylem Başlığı</th>
                                <th class="text-center">KPI</th>
                                <th class="text-center">Bu Yıl</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($actionProgress as $ap): ?>
                            <?php $sl = $statusLabels[$ap['status']] ?? ['label'=>$ap['status'],'cls'=>'']; ?>
                            <tr>
                                <td><code class="action-code"><?= htmlspecialchars($ap['code'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td>
                                    <?= htmlspecialchars($ap['title'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="text-center"><?= (int)$ap['total_kpis'] ?></td>
                                <td class="text-center">
                                    <span class="badge <?= (int)$ap['entries_this_year'] > 0 ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= (int)$ap['entries_this_year'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $sl['cls'] ?>"><?= $sl['label'] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($actionProgress)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Henüz eylem tanımlanmamış.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <div class="col-12 col-xl-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2 text-success"></i>Son Veri Girişleri</span>
                <?php if ($isAdmin): ?>
                <a href="<?= BASE_PATH ?>/public/admin/entries.php" class="btn btn-sm btn-outline-success">Tümünü Gör</a>
                <?php else: ?>
                <a href="<?= BASE_PATH ?>/public/department/my_entries.php" class="btn btn-sm btn-outline-success">Tümünü Gör</a>
                <?php endif; ?>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($recentEntries)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        Henüz veri girişi yok.
                    </div>
                <?php endif; ?>
                <?php foreach ($recentEntries as $re): ?>
                <div class="list-group-item px-3 py-3" style="border-color:var(--border-light);">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;
                            background:<?= $re['is_verified'] ? 'var(--clr-accent-dim)' : '#FFFbeb' ?>;
                            color:<?= $re['is_verified'] ? '#00A669' : '#D97706' ?>; font-size:.85rem;">
                            <i class="bi bi-<?= $re['is_verified'] ? 'check-circle-fill' : 'hourglass-split' ?>"></i>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div class="fw-semibold text-sm-plus cell-truncate">
                                <?= htmlspecialchars($re['kpi_name'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="text-muted text-xs">
                                <code class="action-code" style="font-size:.65rem; padding:.1rem .35rem;"><?= htmlspecialchars($re['action_code'], ENT_QUOTES, 'UTF-8') ?></code>
                                <?php if ($isAdmin && isset($re['dept_name'])): ?>
                                · <?= htmlspecialchars($re['dept_name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                                <?php if ($isAdmin && isset($re['user_name'])): ?>
                                · <?= htmlspecialchars($re['user_name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                                · <?= date('d.m.Y', strtotime($re['created_at'])) ?>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0 ms-2">
                            <div style="font-family:'Outfit',sans-serif; font-weight:700; font-size:.9rem; color:var(--text-display);">
                                <?= number_format((float)$re['value'], 2) ?>
                                <small class="text-muted text-2xs" style="font-weight:600;"><?= htmlspecialchars($re['unit'], ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                            <span style="font-size:.65rem; color:var(--text-tertiary); background:var(--bg-hover); padding:.1rem .4rem; border-radius:99px;"><?= $re['year'] ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<?php
if ($isAdmin) {
    $statusColors = [
        'planned'   => '#9ca3af',
        'ongoing'   => '#3b82f6',
        'completed' => '#10b981',
    ];
    $chartStatusLabels = [];
    $chartStatusValues = [];
    $chartStatusColors = [];
    foreach ($statusDist as $sd) {
        $chartStatusLabels[] = $statusLabels[$sd['status']]['label'] ?? $sd['status'];
        $chartStatusValues[] = (int)$sd['cnt'];
        $chartStatusColors[] = $statusColors[$sd['status']] ?? '#6b7280';
    }

    $chartCatLabels = array_column($catDist, 'category');
    $chartCatValues = array_map('intval', array_column($catDist, 'cnt'));

    $extraJs = "<script>
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: " . json_encode($chartStatusLabels) . ",
        datasets: [{
            data: " . json_encode($chartStatusValues) . ",
            backgroundColor: " . json_encode($chartStatusColors) . ",
            borderWidth: 0,
            spacing: 2,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: { position: 'bottom', labels: { padding: 16, font: { size: 12, family: 'Manrope', weight: '600' }, color: '#5C6573', usePointStyle: true, pointStyle: 'circle' } }
        }
    }
});

new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: " . json_encode($chartCatLabels) . ",
        datasets: [{
            data: " . json_encode($chartCatValues) . ",
            backgroundColor: '#00D084',
            borderRadius: 4,
            maxBarThickness: 24
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: '#E6E8EB', drawBorder: false }, ticks: { font: { size: 11, family: 'Manrope', weight: '600' }, color: '#8B94A3' } },
            y: { grid: { display: false, drawBorder: false }, ticks: { font: { size: 11, family: 'Manrope', weight: '600' }, color: '#5C6573' } }
        }
    }
});
</script>";
}
?>

<?php require_once APP_ROOT . '/templates/shared/footer.php'; ?>
