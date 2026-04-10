<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireAdmin();

$pdo       = Database::getInstance()->getConnection();
$pageTitle = 'Veri İzleme';
$activeNav = 'monitoring';

$filterYear = (int)($_GET['year'] ?? date('Y'));
$filterDept = (int)($_GET['dept_id'] ?? 0);

$where = ["a.status != 'cancelled'"];
$params = [];

if ($filterDept > 0) {
    $where[] = 'a.responsible_department_id = :dept_id';
    $params[':dept_id'] = $filterDept;
}

$sql = "SELECT a.id, a.code, a.title, a.description, a.performance_indicators,
               a.category, a.status, a.start_year, a.end_year,
               d.name AS dept_name
        FROM   actions a
        JOIN   departments d ON d.id = a.responsible_department_id
        WHERE  " . implode(' AND ', $where) . "
        ORDER  BY a.code";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$actions = $stmt->fetchAll();

$actionIds = array_column($actions, 'id');

if (!empty($actionIds)) {
    $placeholders = implode(',', array_fill(0, count($actionIds), '?'));

    $actStmt = $pdo->prepare(
        "SELECT act.id, act.action_id, act.title, act.sub_actions, act.department_id,
                d.name AS dept_name
         FROM   activities act
         JOIN   departments d ON d.id = act.department_id
         WHERE  act.action_id IN ({$placeholders}) AND act.is_active = 1
         ORDER  BY act.sort_order, act.id"
    );
    $actStmt->execute($actionIds);
    $activitiesByAction = [];
    foreach ($actStmt->fetchAll() as $row) {
        $activitiesByAction[(int)$row['action_id']][] = $row;
    }

    $kpiStmt = $pdo->prepare(
        "SELECT k.id, k.action_id, k.name, k.unit, k.target_value, k.target_label,
                k.is_cumulative, k.activity_id,
                de.value AS reported_value, de.notes AS data_notes,
                de.is_verified, de.year AS entry_year,
                u.full_name AS entered_by
         FROM   kpis k
         LEFT JOIN data_entries de ON de.kpi_id = k.id AND de.year = ?
         LEFT JOIN users u ON u.id = de.entered_by
         WHERE  k.action_id IN ({$placeholders}) AND k.is_active = 1
         ORDER  BY k.activity_id, k.id"
    );
    $kpiStmt->execute(array_merge([$filterYear], $actionIds));
    $kpisByAction = [];
    foreach ($kpiStmt->fetchAll() as $row) {
        $kpisByAction[(int)$row['action_id']][] = $row;
    }

    $adStmt = $pdo->prepare(
        "SELECT ad.action_id, d.name, ad.role_type
         FROM   action_departments ad
         JOIN   departments d ON d.id = ad.department_id
         WHERE  ad.action_id IN ({$placeholders})
         ORDER  BY ad.role_type, d.name"
    );
    $adStmt->execute($actionIds);
    $deptsByAction = [];
    foreach ($adStmt->fetchAll() as $row) {
        $deptsByAction[(int)$row['action_id']][] = $row;
    }

    foreach ($actions as &$action) {
        $aid = (int)$action['id'];
        $action['activities']  = $activitiesByAction[$aid] ?? [];
        $action['kpis']        = $kpisByAction[$aid] ?? [];
        $action['departments'] = $deptsByAction[$aid] ?? [];
    }
    unset($action);
} else {
    foreach ($actions as &$action) {
        $action['activities']  = [];
        $action['kpis']        = [];
        $action['departments'] = [];
    }
    unset($action);
}

$departments = $pdo->query("SELECT id, name FROM departments WHERE is_active=1 AND slug NOT LIKE '%-dis-paydas' ORDER BY name")->fetchAll();
$years = range((int)date('Y'), 2020);

$statusLabels = [
    'planned'   => ['label' => 'Planlandı',   'cls' => 'badge-planned'],
    'ongoing'   => ['label' => 'Devam Ediyor', 'cls' => 'badge-ongoing'],
    'completed' => ['label' => 'Tamamlandı',  'cls' => 'badge-completed'],
    'cancelled' => ['label' => 'İptal',        'cls' => 'badge-cancelled'],
];

require_once APP_ROOT . '/templates/shared/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0">Veri İzleme Paneli</h5>
        <small class="text-muted"><?= count($actions) ?> eylem · <?= $filterYear ?> yılı verileri</small>
    </div>
    <a href="<?= BASE_PATH ?>/public/admin/export.php?year=<?= $filterYear ?>" class="btn btn-success">
        <i class="bi bi-download me-1"></i>Excel İndir
    </a>
</div>

<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end filter-bar">
            <div class="col-auto">
                <label class="form-label mb-1 small">Yıl</label>
                <select name="year" class="form-select filter-year">
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $filterYear===$y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small">Müdürlük</label>
                <select name="dept_id" class="form-select filter-dept">
                    <option value="0">Tümü</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filterDept===(int)$d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-link">Seçimleri Uygula</button>
                <a href="monitoring.php" class="btn btn-link">Filtreleri Temizle</a>
            </div>
        </form>
    </div>
</div>

<div class="accordion" id="monitoringAccordion">
    <?php foreach ($actions as $i => $a): ?>
    <?php
        $sl = $statusLabels[$a['status']] ?? ['label'=>$a['status'],'cls'=>''];
        $totalKpis = count($a['kpis']);
        $enteredKpis = count(array_filter($a['kpis'], fn($k) => $k['reported_value'] !== null));
        $pct = $totalKpis > 0 ? (int)(($enteredKpis / $totalKpis) * 100) : 0;
        $barCls = $pct >= 80 ? 'bg-success' : ($pct >= 40 ? 'bg-warning' : ($pct > 0 ? 'bg-info' : 'bg-danger'));
    ?>
    <div class="card action-card mb-3">
        <div class="card-header p-0" id="heading<?= $i ?>">
            <button class="btn w-100 text-start p-3 <?= $i > 0 ? 'collapsed' : '' ?>"
                    data-bs-toggle="collapse" data-bs-target="#collapse<?= $i ?>"
                    aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <code class="action-code me-2"><?= htmlspecialchars($a['code'], ENT_QUOTES, 'UTF-8') ?></code>
                        <span class="fw-semibold"><?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-3">
                        <span class="badge <?= $sl['cls'] ?>"><?= $sl['label'] ?></span>
                        <span class="badge bg-secondary-subtle text-secondary"><?= $enteredKpis ?>/<?= $totalKpis ?> KPI</span>
                    </div>
                </div>
                <?php if ($totalKpis > 0): ?>
                <div class="d-flex align-items-center gap-2 mt-2">
                    <div class="progress flex-grow-1" style="height:4px;">
                        <div class="progress-bar <?= $barCls ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <small class="text-muted text-xs"><?= $pct ?>%</small>
                </div>
                <?php endif; ?>
            </button>
        </div>

        <div id="collapse<?= $i ?>" class="collapse <?= $i === 0 ? 'show' : '' ?>"
             data-bs-parent="#monitoringAccordion">
            <div class="card-body">
                <?php if ($a['description']): ?>
                <div class="mb-3 p-3" style="background:var(--clr-primary-50); border-radius:var(--radius-sm);">
                    <small class="fw-semibold text-success d-block mb-1">Proje Açıklaması</small>
                    <p class="mb-0" style="font-size:.84rem;"><?= htmlspecialchars($a['description'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php endif; ?>

                <?php if ($a['performance_indicators']): ?>
                <div class="mb-3 p-3" style="background:#eff6ff; border-radius:var(--radius-sm);">
                    <small class="fw-semibold text-primary d-block mb-1">Performans Göstergeleri</small>
                    <p class="mb-0" style="font-size:.84rem;"><?= htmlspecialchars($a['performance_indicators'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($a['departments'])): ?>
                <div class="mb-3">
                    <small class="fw-semibold text-muted d-block mb-1">Sorumlu Müdürlükler</small>
                    <?php foreach ($a['departments'] as $ad): ?>
                        <span class="badge <?= $ad['role_type'] === 'primary' ? 'bg-success' : 'bg-secondary-subtle text-secondary' ?> me-1">
                            <?= htmlspecialchars($ad['name'], ENT_QUOTES, 'UTF-8') ?>
                            <?= $ad['role_type'] === 'primary' ? '(Birincil)' : '' ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($a['kpis'])): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:.84rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Bildirilecek Veri</th>
                                <th>Yıllık Hedef</th>
                                <th>Birimi</th>
                                <th class="text-center">Raporlanan Yıl</th>
                                <th class="text-end" style="background:#fff3cd;">Raporlanan Veri</th>
                                <th style="background:#fff3cd;">Veri Açıklaması</th>
                                <th class="text-center">Onay</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($a['kpis'] as $k): ?>
                            <?php $hasData = $k['reported_value'] !== null; ?>
                            <tr>
                                <td>
                                    <div class="fw-medium"><?= htmlspecialchars($k['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if ($k['is_cumulative']): ?>
                                    <small class="text-info"><i class="bi bi-layers me-1"></i>Kümülatif</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($k['target_label']): ?>
                                        <small><?= htmlspecialchars($k['target_label'], ENT_QUOTES, 'UTF-8') ?></small>
                                    <?php elseif ($k['target_value'] !== null): ?>
                                        <?= number_format((float)$k['target_value'], 0) ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($k['unit'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="text-center">
                                    <?= $hasData ? $k['entry_year'] : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td class="text-end fw-bold" style="background:<?= $hasData ? '#fffbeb' : '#fff3cd' ?>;">
                                    <?php if ($hasData): ?>
                                        <span class="text-success"><?= number_format((float)$k['reported_value'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Girilmedi</span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width:200px; background:<?= $hasData ? '#fffbeb' : '#fff3cd' ?>;">
                                    <?php if ($hasData && $k['data_notes']): ?>
                                        <small><?= nl2br(htmlspecialchars(mb_strimwidth($k['data_notes'], 0, 100, '…'), ENT_QUOTES, 'UTF-8')) ?></small>
                                    <?php elseif ($hasData): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <span class="text-danger">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!$hasData): ?>
                                        <span class="text-muted">—</span>
                                    <?php elseif ($k['is_verified']): ?>
                                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle"></i></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning badge-pending"><i class="bi bi-hourglass"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-3 text-md">
                    <i class="bi bi-info-circle me-1"></i>Henüz KPI tanımlanmamış.
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($actions)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-search"></i>
        Filtrelere uygun eylem bulunamadı.
    </div>
</div>
<?php endif; ?>

<?php require_once APP_ROOT . '/templates/shared/footer.php'; ?>
