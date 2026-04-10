<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$pdo      = Database::getInstance()->getConnection();
$actionId = (int)($_GET['action_id'] ?? 0);
$errors   = [];

if ($actionId <= 0) {
    Flash::error('Geçerli bir eylem seçilmedi.');
    header('Location: ' . BASE_PATH . '/public/department/my_actions.php');
    exit;
}

if (!Auth::canAccessAction($pdo, $actionId)) {
    Auth::denyAccess($pdo, 'actions', $actionId, [
        'message' => 'Bu eyleme faaliyet ekleme yetkiniz bulunmuyor.',
    ]);
}

$stmt = $pdo->prepare('SELECT id, code, title FROM actions WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $actionId]);
$action = $stmt->fetch();

if (!$action) {
    Flash::error('Eylem bulunamadı.');
    header('Location: ' . BASE_PATH . '/public/department/my_actions.php');
    exit;
}

$pageTitle = 'Yeni Faaliyet Ekle';
$activeNav = 'my_actions';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $title      = Validator::text($_POST['title'] ?? '', 500);
    $subActions = Validator::textarea($_POST['sub_actions'] ?? '', 5000);
    $deptId     = Auth::getDepartmentId();

    if ($title === '') {
        $errors[] = 'Faaliyet başlığı boş olamaz.';
    }

    if (empty($errors)) {
        $maxSoStr = $pdo->prepare('SELECT MAX(sort_order) FROM activities WHERE action_id = :aid');
        $maxSoStr->execute([':aid' => $actionId]);
        $nextSo = (int)$maxSoStr->fetchColumn() + 1;

        $stmt = $pdo->prepare(
            "INSERT INTO activities (action_id, department_id, title, sub_actions, sort_order, created_by)
             VALUES (:aid, :did, :title, :sub, :so, :cb)"
        );
        $stmt->execute([
            ':aid'   => $actionId,
            ':did'   => $deptId,
            ':title' => $title,
            ':sub'   => $subActions ?: null,
            ':so'    => $nextSo,
            ':cb'    => Auth::getUserId(),
        ]);

        $newActivityId = (int) $pdo->lastInsertId();
        AuditLog::log($pdo, 'create', 'activities', $newActivityId, null, [
            'action_id' => $actionId,
            'department_id' => $deptId,
            'title' => $title,
            'sub_actions' => $subActions ?: null,
            'sort_order' => $nextSo,
            'created_via' => 'department_portal',
        ]);

        Flash::success('Yeni faaliyet başarıyla eklendi. (Silme / düzenleme yetkisi sistem yöneticisindedir.)');
        header('Location: ' . BASE_PATH . '/public/department/my_actions.php');
        exit;
    }
}

require_once APP_ROOT . '/templates/shared/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= BASE_PATH ?>/public/department/my_actions.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h5 class="fw-bold mb-0">Yeni Faaliyet Ekle</h5>
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

<div class="alert alert-info" style="max-width:600px;">
    <i class="bi bi-info-circle me-2"></i>
    Müdürlükler faaliyet ekleyebilir, ancak eklenen faaliyetleri sadece Sistem Yöneticisi silebilir veya düzenleyebilir.
</div>

<div class="card" style="max-width:600px;">
    <div class="card-body">
        <form method="POST" novalidate>
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Faaliyet Başlığı <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control"
                       value="<?= htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Sorumlu olduğu faaliyet detayı..." required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Alt Eylemler (Opsiyonel)</label>
                <textarea name="sub_actions" class="form-control" rows="3"
                          placeholder="Gerçekleştirilecek eylemler vb..."><?= htmlspecialchars($_POST['sub_actions'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-plus-lg me-1"></i>Oluştur
                </button>
                <a href="<?= BASE_PATH ?>/public/department/my_actions.php" class="btn btn-outline-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<?php require_once APP_ROOT . '/templates/shared/footer.php'; ?>
