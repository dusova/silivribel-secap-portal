<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
Auth::requireAdmin();

$pdo       = Database::getInstance()->getConnection();
$pageTitle = 'Eylem Yönetimi';
$activeNav = 'actions';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soft_delete_id'])) {
    Auth::requireSuperAdmin();
    Csrf::check();
    $targetId = (int) $_POST['soft_delete_id'];
    $reason   = Validator::text($_POST['delete_reason'] ?? '', 500);
    try {
        $snapStmt = $pdo->prepare('SELECT * FROM actions WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $snapStmt->execute([':id' => $targetId]);
        $snapshot = $snapStmt->fetch() ?: [];
        if (empty($snapshot)) {
            Flash::error('Eylem bulunamadı veya zaten silinmiş.');
        } elseif (SoftDelete::delete($pdo, 'actions', $targetId, $reason, $snapshot)) {
            Flash::success('Eylem çöp kutusuna taşındı.');
        } else {
            Flash::error('Silme işlemi gerçekleştirilemedi.');
        }
    } catch (InvalidArgumentException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        error_log('[SECAP][SoftDelete][actions] ' . $e->getMessage());
        Flash::error('Silme işlemi sırasında bir hata oluştu.');
    }
    header('Location: ' . BASE_PATH . '/yonetim/eylemler');
    exit;
}

$filterDept   = (int)($_GET['dept_id']  ?? 0);
$filterStatus = $_GET['status'] ?? '';

$stmt = $pdo->prepare(
    "SELECT a.*, d.name AS dept_name,
            (SELECT COUNT(*) FROM activities act WHERE act.action_id = a.id AND act.is_active = 1 AND act.deleted_at IS NULL) AS activity_count,
            (SELECT COUNT(*) FROM kpis k WHERE k.action_id = a.id AND k.is_active = 1 AND k.deleted_at IS NULL) AS kpi_count
     FROM   actions a
     JOIN   departments d ON d.id = a.responsible_department_id
     WHERE  a.deleted_at IS NULL
       AND  (:dept_filter_on = 0 OR a.responsible_department_id = :dept_id)
       AND  (:status_filter_on = 0 OR a.status = :status_value)
     ORDER  BY a.code"
);
$stmt->execute([
    ':dept_filter_on' => $filterDept > 0 ? 1 : 0,
    ':dept_id' => $filterDept,
    ':status_filter_on' => $filterStatus !== '' ? 1 : 0,
    ':status_value' => $filterStatus,
]);
$actions = $stmt->fetchAll();

$departments = $pdo->query("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();

$statusLabels = [
    'planned'   => ['label' => 'Planlandı',    'cls' => 'rozet-planli'],
    'ongoing'   => ['label' => 'Devam Ediyor', 'cls' => 'rozet-devam'],
    'completed' => ['label' => 'Tamamlandı',   'cls' => 'rozet-tamamlandi'],
    'cancelled' => ['label' => 'İptal',        'cls' => 'rozet-iptal'],
];

require_once APP_ROOT . '/uygulama/yerlesim/ust.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0">Eylemler</h5>
        <small class="text-muted"><?= count($actions) ?> eylem</small>
    </div>
    <a href="<?= BASE_PATH ?>/yonetim/eylem-formu" class="btn btn-success">
        <i class="bi bi-plus-lg me-1"></i>Yeni Eylem
    </a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end filtre-cubugu">
            <div class="col-auto">
                <label class="form-label mb-1 small">Müdürlük</label>
                <select name="dept_id" class="form-select filtre-mudurluk">
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
                <select name="status" class="form-select filtre-durum">
                    <option value="">Tümü</option>
                    <?php foreach ($statusLabels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filterStatus===$k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-link">Seçimleri Uygula</button>
                <a href="actions" class="btn btn-link">Filtreleri Temizle</a>
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
                        <td><code class="eylem-kodu"><?= htmlspecialchars($a['code'], ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td>
                            <?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($a['category']): ?>
                            <br><small class="text-muted yazi-xs"><?= htmlspecialchars($a['category'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="yazi-kucuk">
                            <?= htmlspecialchars($a['dept_name'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="text-center"><span class="badge bg-secondary-subtle text-secondary"><?= (int)$a['activity_count'] ?></span></td>
                        <td class="text-center"><span class="badge bg-primary-subtle text-primary"><?= (int)$a['kpi_count'] ?></span></td>
                        <td class="yazi-kucuk" style="white-space:nowrap;"><?= $a['start_year'] ?>–<?= $a['end_year'] ?? '?' ?></td>
                        <td class="text-center"><span class="badge <?= $sl['cls'] ?>"><?= $sl['label'] ?></span></td>
                        <td class="text-end">
                            <a href="<?= BASE_PATH ?>/yonetim/eylem-formu?id=<?= $a['id'] ?>"
                               class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="bi bi-pencil"></i></a>
                            <?php if (Auth::isSuperAdmin()): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2"
                                    data-bs-toggle="modal" data-bs-target="#softDeleteModal"
                                    data-sd-id="<?= (int) $a['id'] ?>"
                                    data-sd-label="<?= htmlspecialchars($a['code'] . ' — ' . $a['title'], ENT_QUOTES, 'UTF-8') ?>"
                                    title="Çöp Kutusuna Taşı">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
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

<?php if (Auth::isSuperAdmin()):
    $sdFormAction = BASE_PATH . '/yonetim/eylemler';
    $sdTitle      = 'Eylemi Çöp Kutusuna Taşı';
    $sdLabel      = 'Eylem';
    require APP_ROOT . '/uygulama/parcalar/cop-kutusu-modal.php';
endif; ?>

<?php require_once APP_ROOT . '/uygulama/yerlesim/alt.php'; ?>
