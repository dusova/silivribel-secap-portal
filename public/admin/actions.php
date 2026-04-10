<?php

require_once __DIR__ . '/../../bootstrap.php';
Auth::requireAdmin();

$pdo       = Database::getInstance()->getConnection();
$pageTitle = 'Eylem Yönetimi';
$activeNav = 'actions';

$filterDept   = (int)($_GET['dept_id']  ?? 0);
$filterStatus = $_GET['status'] ?? '';

$where  = ['1=1'];
$params = [];
if ($filterDept > 0)    { $where[] = 'a.responsible_department_id = :dept'; $params[':dept'] = $filterDept; }
if ($filterStatus !== ''){ $where[] = 'a.status = :st';                     $params[':st'] = $filterStatus; }

$stmt = $pdo->prepare(
    "SELECT a.*, d.name AS dept_name,
            (SELECT COUNT(*) FROM activities act WHERE act.action_id = a.id AND act.is_active = 1) AS activity_count,
            (SELECT COUNT(*) FROM kpis k WHERE k.action_id = a.id AND k.is_active = 1) AS kpi_count
     FROM   actions a
     JOIN   departments d ON d.id = a.responsible_department_id
     WHERE  " . implode(' AND ', $where) . "
     ORDER  BY a.code"
);
$stmt->execute($params);
$actions = $stmt->fetchAll();

$departments = $pdo->query("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();

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
        <h5 class="fw-bold mb-0">Eylemler</h5>
        <small class="text-muted"><?= count($actions) ?> eylem</small>
    </div>
    <a href="<?= BASE_PATH ?>/public/admin/action_form.php" class="btn btn-success">
        <i class="bi bi-plus-lg me-1"></i>Yeni Eylem
    </a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end filter-bar">
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
                <label class="form-label mb-1 small">Durum</label>
                <select name="status" class="form-select filter-status">
                    <option value="">Tümü</option>
                    <?php foreach ($statusLabels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filterStatus===$k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-link">Seçimleri Uygula</button>
                <a href="actions.php" class="btn btn-link">Filtreleri Temizle</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kod</th>
                        <th>Eylem Başlığı</th>
                        <th>Müdürlük</th>
                        <th class="text-center">Faaliyet</th>
                        <th class="text-center">KPI</th>
                        <th>Dönem</th>
                        <th class="text-center">Durum</th>
                        <th class="text-end">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($actions as $a): ?>
                    <?php $sl = $statusLabels[$a['status']] ?? ['label'=>$a['status'],'cls'=>'']; ?>
                    <tr>
                        <td><code class="action-code"><?= htmlspecialchars($a['code'], ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td>
                            <?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($a['category']): ?>
                            <br><small class="text-muted text-xs"><?= htmlspecialchars($a['category'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm">
                            <?= htmlspecialchars($a['dept_name'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="text-center"><span class="badge bg-secondary-subtle text-secondary"><?= (int)$a['activity_count'] ?></span></td>
                        <td class="text-center"><span class="badge bg-primary-subtle text-primary"><?= (int)$a['kpi_count'] ?></span></td>
                        <td class="text-sm" style="white-space:nowrap;"><?= $a['start_year'] ?>–<?= $a['end_year'] ?? '?' ?></td>
                        <td class="text-center"><span class="badge <?= $sl['cls'] ?>"><?= $sl['label'] ?></span></td>
                        <td class="text-end">
                            <a href="<?= BASE_PATH ?>/public/admin/action_form.php?id=<?= $a['id'] ?>"
                               class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($actions)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">Filtrelere uygun eylem bulunamadı.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/templates/shared/footer.php'; ?>
