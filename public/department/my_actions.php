<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$pdo       = Database::getInstance()->getConnection();
$pageTitle = 'Eylemlerim';
$activeNav = 'my_actions';

$accessibleActionIds = Auth::getAccessibleActionIds($pdo);
$actionRows = [];

if (!empty($accessibleActionIds)) {
    $placeholders = Auth::getSqlPlaceholders($accessibleActionIds);
    $stmt = $pdo->prepare(
        "SELECT a.id AS action_id, a.code, a.title, a.category, a.description,
                a.start_year, a.end_year, a.status
         FROM   actions a
         WHERE  a.id IN ({$placeholders})
         ORDER  BY a.code"
    );
    $stmt->execute($accessibleActionIds);
    $actionRows = $stmt->fetchAll();
}

$actions = [];
foreach ($actionRows as $row) {
    $aid = $row['action_id'];
    $a = [
        'id'         => $aid,
        'code'       => $row['code'],
        'title'      => $row['title'],
        'category'   => $row['category'],
        'description'=> $row['description'],
        'start_year' => $row['start_year'],
        'end_year'   => $row['end_year'],
        'status'     => $row['status'],
        'activities' => [],
        'kpis'       => [],
    ];

    $actStmt = $pdo->prepare(
        "SELECT act.id, act.title, act.sub_actions, d.name AS dept_name
         FROM   activities act
         JOIN   departments d ON d.id = act.department_id
         WHERE  act.action_id = :action_id AND act.is_active = 1
         ORDER  BY act.sort_order, act.id"
    );
    $actStmt->execute([':action_id' => $aid]);
    $a['activities'] = $actStmt->fetchAll();

    $kpiStmt = $pdo->prepare(
        "SELECT k.id AS kpi_id, k.name AS kpi_name, k.unit, k.target_value,
                k.baseline_value, k.is_cumulative, k.activity_id,
                de.value AS current_value, de.year AS entry_year, de.is_verified,
                de.id AS entry_id
         FROM   kpis k
         LEFT JOIN data_entries de ON de.kpi_id = k.id AND de.year = YEAR(NOW())
         WHERE  k.action_id = :action_id AND k.is_active = 1
         ORDER  BY k.activity_id, k.id"
    );
    $kpiStmt->execute([':action_id' => $aid]);
    $a['kpis'] = $kpiStmt->fetchAll();

    $actions[] = $a;
}

$statusLabels = [
    'planned'   => ['label' => 'Planlandı',    'cls' => 'badge-planned'],
    'ongoing'   => ['label' => 'Devam Ediyor', 'cls' => 'badge-ongoing'],
    'completed' => ['label' => 'Tamamlandı',   'cls' => 'badge-completed'],
    'cancelled' => ['label' => 'İptal',        'cls' => 'badge-cancelled'],
];

require_once APP_ROOT . '/templates/shared/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0">Eylemlerim</h5>
        <small class="text-muted"><?= count($actions) ?> eylem · <?= date('Y') ?> yılı veri durumu</small>
    </div>
    <a href="<?= BASE_PATH ?>/public/department/data_form.php" class="btn btn-success">
        <i class="bi bi-plus-lg me-1"></i>Veri Gir
    </a>
</div>

<?php if (empty($actions)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-inbox"></i>
        Müdürlüğünüze tanımlanmış eylem bulunmuyor.
    </div>
</div>
<?php else: ?>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kod</th>
                        <th>Eylem Başlığı</th>
                        <th class="text-center">KPI</th>
                        <th class="text-center">Bu Yıl</th>
                        <th>Doluluk</th>
                        <th class="text-center">Durum</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($actions as $a): ?>
                    <?php
                        $sl      = $statusLabels[$a['status']] ?? ['label'=>$a['status'],'cls'=>''];
                        $total   = count($a['kpis']);
                        $entered = count(array_filter($a['kpis'], fn($k) => $k['current_value'] !== null));
                        $pct     = $total > 0 ? (int)(($entered / $total) * 100) : 0;
                        $barCls  = $pct >= 80 ? 'bg-success' : ($pct >= 40 ? 'bg-warning' : 'bg-danger');
                    ?>
                    <tr>
                        <td><code class="action-code"><?= htmlspecialchars($a['code'], ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td>
                            <?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($a['category']): ?>
                            <br><small class="text-muted text-xs"><?= htmlspecialchars($a['category'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><span class="badge bg-primary-subtle text-primary"><?= $total ?></span></td>
                        <td class="text-center">
                            <span class="badge <?= $entered > 0 ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>">
                                <?= $entered ?>/<?= $total ?>
                            </span>
                        </td>
                        <td style="min-width:120px;">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar <?= $barCls ?>" style="width:<?= $pct ?>%"></div>
                                </div>
                                <small class="text-muted" style="font-size:.75rem; width:32px;"><?= $pct ?>%</small>
                            </div>
                        </td>
                        <td class="text-center"><span class="badge <?= $sl['cls'] ?>"><?= $sl['label'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($actions as $a): ?>
<?php
    $sl      = $statusLabels[$a['status']] ?? ['label'=>$a['status'],'cls'=>''];
    $total   = count($a['kpis']);
    $entered = count(array_filter($a['kpis'], fn($k) => $k['current_value'] !== null));
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div style="min-width:0;">
            <code class="action-code me-2"><?= htmlspecialchars($a['code'], ENT_QUOTES, 'UTF-8') ?></code>
            <span class="fw-semibold"><?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-2">
            <span class="badge <?= $sl['cls'] ?>"><?= $sl['label'] ?></span>
            <small class="text-muted" style="white-space:nowrap;"><?= $a['start_year'] ?>–<?= $a['end_year'] ?? '?' ?></small>
        </div>
    </div>

    <?php if (!empty($a['activities'])): ?>
    <div class="px-3 py-2" style="background:var(--clr-primary-50); border-bottom:1px solid var(--clr-neutral-200);">
        <div class="d-flex justify-content-between align-items-center">
            <small class="fw-semibold text-success"><i class="bi bi-layers me-1"></i>Faaliyetler</small>
            <a href="<?= BASE_PATH ?>/public/department/add_activity.php?action_id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-success py-0 px-2 text-2xs">
                <i class="bi bi-plus"></i> Ekle
            </a>
        </div>
        <?php foreach ($a['activities'] as $act): ?>
        <div class="d-flex align-items-start gap-2 mt-1" style="font-size:.82rem;">
            <i class="bi bi-dot text-success" style="font-size:1.2rem; line-height:1.2;"></i>
            <div>
                <span class="fw-medium"><?= htmlspecialchars($act['title'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="text-muted ms-1" style="font-size:.76rem;">— <?= htmlspecialchars($act['dept_name'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($act['sub_actions'] && $act['sub_actions'] !== '-'): ?>
                <div class="text-muted" style="font-size:.76rem;"><?= htmlspecialchars($act['sub_actions'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($a['kpis'])): ?>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-md">
                <thead class="table-light">
                    <tr>
                        <th>KPI</th>
                        <th class="text-end">Hedef</th>
                        <th class="text-end"><?= date('Y') ?> Değeri</th>
                        <th class="text-center">Onay</th>
                        <th class="text-center">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($a['kpis'] as $k): ?>
                    <?php
                        $hasEntry = $k['current_value'] !== null;
                        $progress = null;
                        if ($hasEntry && $k['target_value'] > 0) {
                            $progress = min(100, (float)$k['current_value'] / (float)$k['target_value'] * 100);
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="fw-medium"><?= htmlspecialchars($k['kpi_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <small class="text-muted">Birim: <?= htmlspecialchars($k['unit'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php if ($k['is_cumulative']): ?>
                            <small class="text-info ms-1"><i class="bi bi-layers"></i> Kümülatif</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?= $k['target_value'] !== null
                                ? number_format((float)$k['target_value'],2) . ' <small class="text-muted">' . htmlspecialchars($k['unit'],ENT_QUOTES,'UTF-8') . '</small>'
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td class="text-end">
                            <?php if ($hasEntry): ?>
                                <span class="fw-bold text-success">
                                    <?= number_format((float)$k['current_value'],2) ?>
                                    <small class="text-muted"><?= htmlspecialchars($k['unit'],ENT_QUOTES,'UTF-8') ?></small>
                                </span>
                                <?php if ($progress !== null): ?>
                                <div class="text-muted text-xs">(%<?= round($progress,1) ?> hedefe ulaşıldı)</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-danger"><i class="bi bi-dash-circle me-1"></i>Girilmedi</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!$hasEntry): ?>
                                <span class="text-muted">—</span>
                            <?php elseif ($k['is_verified']): ?>
                                <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Onaylı</span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning badge-pending"><i class="bi bi-hourglass me-1"></i>Bekliyor</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="<?= BASE_PATH ?>/public/department/data_form.php?kpi_id=<?= $k['kpi_id'] ?>&year=<?= date('Y') ?>"
                               class="btn btn-sm <?= $hasEntry ? 'btn-outline-secondary' : 'btn-success' ?> py-0 px-2">
                                <i class="bi bi-<?= $hasEntry ? 'pencil' : 'plus' ?>"></i>
                                <?= $hasEntry ? 'Düzenle' : 'Gir' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="card-body text-center text-muted py-3 text-md">
        Bu eylem için henüz KPI tanımlanmamış. Admin ile iletişime geçin.
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once APP_ROOT . '/templates/shared/footer.php'; ?>
