<?php

require_once __DIR__ . '/../../bootstrap.php';
Auth::requireAdmin();

$pdo    = Database::getInstance()->getConnection();
$id     = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$activity = null;
$errors = [];

$actionId = (int)($_GET['action_id'] ?? 0);

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM activities WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $activity = $stmt->fetch();
    if (!$activity) {
        Flash::error('Faaliyet bulunamadı.');
        header('Location: ' . BASE_PATH . '/public/admin/actions.php');
        exit;
    }
    $actionId = (int)$activity['action_id'];
}

$action = null;
if ($actionId > 0) {
    $stmt = $pdo->prepare('SELECT id, code, title FROM actions WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $actionId]);
    $action = $stmt->fetch();
}

if (!$action) {
    Flash::error('Geçerli bir eylem bulunamadı.');
    header('Location: ' . BASE_PATH . '/public/admin/actions.php');
    exit;
}

$pageTitle = $isEdit ? 'Faaliyet Düzenle' : 'Yeni Faaliyet';
$activeNav = 'actions';
$departments = $pdo->query("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $data = [
        'department_id' => (int)($_POST['department_id'] ?? 0),
        'title'         => Validator::text($_POST['title'] ?? '', 500),
        'sub_actions'   => Validator::textarea($_POST['sub_actions'] ?? '', 5000),
        'sort_order'    => (int)($_POST['sort_order'] ?? 0),
    ];

    if ($data['department_id'] <= 0) $errors[] = 'Müdürlük seçilmeli.';
    if ($data['title'] === '')       $errors[] = 'Faaliyet başlığı boş olamaz.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                $oldAudit = [
                    'department_id' => (int) $activity['department_id'],
                    'title' => $activity['title'],
                    'sub_actions' => $activity['sub_actions'],
                    'sort_order' => (int) $activity['sort_order'],
                ];

                $stmt = $pdo->prepare(
                    "UPDATE activities SET department_id=:dept, title=:title, sub_actions=:sub, sort_order=:so WHERE id=:id"
                );
                $stmt->execute([
                    ':dept' => $data['department_id'], ':title' => $data['title'],
                    ':sub' => $data['sub_actions'] ?: null, ':so' => $data['sort_order'], ':id' => $id
                ]);

                AuditLog::log($pdo, 'update', 'activities', $id, $oldAudit, [
                    'department_id' => $data['department_id'],
                    'title' => $data['title'],
                    'sub_actions' => $data['sub_actions'] ?: null,
                    'sort_order' => $data['sort_order'],
                ]);
                Flash::success('Faaliyet güncellendi.');
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO activities (action_id, department_id, title, sub_actions, sort_order, created_by)
                     VALUES (:action_id, :dept, :title, :sub, :so, :cb)"
                );
                $stmt->execute([
                    ':action_id' => $actionId, ':dept' => $data['department_id'],
                    ':title' => $data['title'], ':sub' => $data['sub_actions'] ?: null,
                    ':so' => $data['sort_order'], ':cb' => Auth::getUserId()
                ]);
                $newActivityId = (int) $pdo->lastInsertId();
                AuditLog::log($pdo, 'create', 'activities', $newActivityId, null, [
                    'action_id' => $actionId,
                    'department_id' => $data['department_id'],
                    'title' => $data['title'],
                    'sub_actions' => $data['sub_actions'] ?: null,
                    'sort_order' => $data['sort_order'],
                ]);
                Flash::success('Faaliyet oluşturuldu.');
            }

            $pdo->commit();
            header("Location: " . BASE_PATH . "/public/admin/action_form.php?id={$actionId}");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[SECAP][DB] activity_form hatası: ' . $e->getMessage());
            $errors[] = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }
    $activity = array_merge($activity ?? [], $data);
}

$f = fn(string $k) => htmlspecialchars((string)($activity[$k] ?? ''), ENT_QUOTES, 'UTF-8');

require_once APP_ROOT . '/templates/shared/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= BASE_PATH ?>/public/admin/action_form.php?id=<?= $actionId ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h5 class="fw-bold mb-0"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h5>
        <small class="text-muted">
            <code class="action-code"><?= htmlspecialchars($action['code'], ENT_QUOTES, 'UTF-8') ?></code>
            <?= htmlspecialchars($action['title'], ENT_QUOTES, 'UTF-8') ?>
        </small>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
    <div class="card-body">
        <form method="POST" novalidate>
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Faaliyet Başlığı <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" value="<?= $f('title') ?>"
                       placeholder="Sorumlu olduğu faaliyet..." required>
                <div class="form-text">Excel'deki "Sorumlu Olduğu Faaliyet" sütunu</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Gerçekleştirilecek Eylemler</label>
                <textarea name="sub_actions" class="form-control" rows="3"
                          placeholder="Alt eylemler, adımlar..."><?= $f('sub_actions') ?></textarea>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Sorumlu Müdürlük <span class="text-danger">*</span></label>
                    <select name="department_id" class="form-select" required>
                        <option value="">Seçiniz…</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= (int)($activity['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Sıra No</label>
                    <input type="number" name="sort_order" class="form-control" min="0"
                           value="<?= (int)($activity['sort_order'] ?? 0) ?>">
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-<?= $isEdit ? 'check-lg' : 'plus-lg' ?> me-1"></i><?= $isEdit ? 'Güncelle' : 'Oluştur' ?>
                </button>
                <a href="<?= BASE_PATH ?>/public/admin/action_form.php?id=<?= $actionId ?>" class="btn btn-outline-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<?php require_once APP_ROOT . '/templates/shared/footer.php'; ?>
