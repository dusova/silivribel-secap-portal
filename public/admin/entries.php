<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireAdmin();

$pdo       = Database::getInstance()->getConnection();
$pageTitle = 'Veri Girişleri';
$activeNav = 'entries';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_verify'])) {
    Csrf::check();
    $ids = $_POST['entry_ids'] ?? [];
    if (!empty($ids)) {
        $intIds = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($intIds), '?'));
        $stmt = $pdo->prepare(
            "UPDATE data_entries SET is_verified = 1, verified_by = ?, verified_at = NOW()
             WHERE id IN ({$placeholders}) AND is_verified = 0"
        );
        $stmt->execute(array_merge([Auth::getUserId()], $intIds));

        foreach ($intIds as $eid) {
            AuditLog::log($pdo, 'verify', 'data_entries', $eid, [
                'is_verified' => 0,
            ], [
                'is_verified' => 1,
                'verified_by' => Auth::getUserId(),
                'bulk' => true,
            ]);
        }

        Flash::success(count($intIds) . ' kayıt onaylandı.');
    }
    header('Location: ' . BASE_PATH . '/public/admin/entries.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_id'])) {
    Csrf::check();
    $eid = (int)$_POST['verify_id'];
    $pdo->prepare(
        'UPDATE data_entries SET is_verified = 1, verified_by = :uid, verified_at = NOW() WHERE id = :id AND is_verified = 0'
    )->execute([':uid' => Auth::getUserId(), ':id' => $eid]);
    AuditLog::log($pdo, 'verify', 'data_entries', $eid, [
        'is_verified' => 0,
    ], [
        'is_verified' => 1,
        'verified_by' => Auth::getUserId(),
    ]);
    Flash::success('Veri onaylandı.');
    header('Location: ' . BASE_PATH . '/public/admin/entries.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unverify_id'])) {
    Csrf::check();
    $eid = (int)$_POST['unverify_id'];
    $pdo->prepare(
        'UPDATE data_entries SET is_verified = 0, verified_by = NULL, verified_at = NULL WHERE id = :id AND is_verified = 1'
    )->execute([':id' => $eid]);
    AuditLog::log($pdo, 'unverify', 'data_entries', $eid, [
        'is_verified' => 1,
    ], [
        'is_verified' => 0,
    ]);
    Flash::warning('Onay kaldırıldı.');
    header('Location: ' . BASE_PATH . '/public/admin/entries.php');
    exit;
}

$filterYear = (int)($_GET['year'] ?? 0);
$filterDept = (int)($_GET['dept_id'] ?? 0);
$filterVerified = $_GET['verified'] ?? '';

$where  = ['1=1'];
$params = [];

if ($filterYear > 0)    { $where[] = 'de.year = :year';           $params[':year'] = $filterYear; }
if ($filterDept > 0)    { $where[] = 'de.department_id = :dept';  $params[':dept'] = $filterDept; }
if ($filterVerified !== '') { $where[] = 'de.is_verified = :ver'; $params[':ver'] = (int)$filterVerified; }

$stmt = $pdo->prepare(
    "SELECT de.*, k.name AS kpi_name, k.unit, a.code AS action_code, a.title AS action_title,
            d.name AS dept_name, u.full_name AS entered_by_name,
            vu.full_name AS verified_by_name
     FROM   data_entries de
     JOIN   kpis    k  ON k.id  = de.kpi_id
     JOIN   actions a  ON a.id  = de.action_id
     JOIN   departments d  ON d.id  = de.department_id
     JOIN   users   u  ON u.id  = de.entered_by
     LEFT JOIN users vu ON vu.id = de.verified_by
     WHERE  " . implode(' AND ', $where) . "
     ORDER  BY de.created_at DESC
     LIMIT 200"
);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$departments = $pdo->query("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();

$pendingCount = $pdo->query("SELECT COUNT(*) FROM data_entries WHERE is_verified = 0")->fetchColumn();

require_once APP_ROOT . '/templates/shared/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0">Veri Girişleri</h5>
        <small class="text-muted"><?= count($entries) ?> kayıt · <?= $pendingCount ?> onay bekliyor</small>
    </div>
    <a href="<?= BASE_PATH ?>/public/department/data_form.php" class="btn btn-success">
        <i class="bi bi-plus-lg me-1"></i>Yeni Giriş
    </a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end filter-bar">
            <div class="col-auto">
                <label class="form-label mb-1 small">Yıl</label>
                <select name="year" class="form-select filter-year">
                    <option value="0">Tümü</option>
                    <?php for ($y = (int)date('Y'); $y >= 2020; $y--): ?>
                    <option value="<?= $y ?>" <?= $filterYear===$y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
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
                <label class="form-label mb-1 small">Onay</label>
                <select name="verified" class="form-select filter-status">
                    <option value="">Tümü</option>
                    <option value="0" <?= $filterVerified==='0' ? 'selected' : '' ?>>Bekleyen</option>
                    <option value="1" <?= $filterVerified==='1' ? 'selected' : '' ?>>Onaylı</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-link">Seçimleri Uygula</button>
                <a href="entries.php" class="btn btn-link">Filtreleri Temizle</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($entries)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-inbox"></i>Filtrelere uygun kayıt bulunamadı.
    </div>
</div>
<?php else: ?>

<form method="POST">
    <?= Csrf::field() ?>

    <div class="d-flex justify-content-end mb-2">
        <button type="submit" name="bulk_verify" value="1" class="btn btn-sm btn-success"
                onclick="return confirm('Seçili kayıtları onaylamak istediğinize emin misiniz?')">
            <i class="bi bi-check2-all me-1"></i>Seçilenleri Onayla
        </button>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:30px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                            <th>Eylem</th>
                            <th>KPI</th>
                            <th>Müdürlük</th>
                            <th class="text-center">Yıl</th>
                            <th class="text-end">Değer</th>
                            <th>Giren</th>
                            <th>Açıklama</th>
                            <th class="text-center">Onay</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td>
                                <?php if (!$e['is_verified']): ?>
                                <input type="checkbox" name="entry_ids[]" value="<?= $e['id'] ?>" class="form-check-input entry-cb">
                                <?php endif; ?>
                            </td>
                            <td><code class="action-code"><?= htmlspecialchars($e['action_code'], ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td>
                                <div class="fw-medium text-sm-plus"><?= htmlspecialchars($e['kpi_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td class="text-sm">
                                <?= htmlspecialchars($e['dept_name'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="text-center fw-bold"><?= $e['year'] ?></td>
                            <td class="text-end fw-bold text-success">
                                <?= number_format((float)$e['value'], 2) ?>
                                <small class="text-muted"><?= htmlspecialchars($e['unit'], ENT_QUOTES, 'UTF-8') ?></small>
                            </td>
                            <td class="text-sm" style="white-space:nowrap;">
                                <?= htmlspecialchars($e['entered_by_name'], ENT_QUOTES, 'UTF-8') ?>
                                <div class="text-muted text-2xs"><?= date('d.m.Y', strtotime($e['created_at'])) ?></div>
                            </td>
                            <td style="max-width:200px; font-size:.78rem;">
                                <?php if ($e['notes']): ?>
                                <span title="<?= htmlspecialchars($e['notes'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(mb_strimwidth($e['notes'], 0, 50, '…'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($e['is_verified']): ?>
                                    <span class="badge bg-success-subtle text-success" title="Onaylayan: <?= htmlspecialchars($e['verified_by_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-check-circle me-1"></i>Onaylı
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning badge-pending">
                                        <i class="bi bi-hourglass me-1"></i>Bekliyor
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($e['is_verified']): ?>
                                <form method="POST" class="d-inline">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="unverify_id" value="<?= $e['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning py-0 px-2" title="Onayı Kaldır">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="POST" class="d-inline">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="verify_id" value="<?= $e['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2" title="Onayla">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>

<?php
$extraJs = '<script>
document.getElementById("selectAll")?.addEventListener("change", function() {
    document.querySelectorAll(".entry-cb").forEach(cb => cb.checked = this.checked);
});
</script>';
?>

<?php require_once APP_ROOT . '/templates/shared/footer.php'; ?>
