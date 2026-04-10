<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireAdmin();

$pdo    = Database::getInstance()->getConnection();
$id     = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$user   = null;
$errors = [];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    if (!$user) {
        Flash::error('Kullanıcı bulunamadı.');
        header('Location: ' . BASE_PATH . '/public/admin/users.php');
        exit;
    }
}

$pageTitle = $isEdit ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı';
$activeNav = 'users';

$departments = $pdo->query(
    "SELECT id, name FROM departments WHERE is_active=1 ORDER BY name"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $data = [
        'department_id' => (int)($_POST['department_id'] ?? 0),
        'username'      => Validator::text($_POST['username'] ?? '', 80),
        'email'         => Validator::email($_POST['email'] ?? ''),
        'full_name'     => Validator::text($_POST['full_name'] ?? '', 150),
        'role'          => Validator::enum($_POST['role'] ?? '', ['admin', 'department_user'], 'department_user'),
        'is_active'     => isset($_POST['is_active']) ? 1 : 0,
    ];
    $password    = $_POST['password']        ?? '';
    $passwordRep = $_POST['password_repeat'] ?? '';

    if ($data['department_id'] <= 0) $errors[] = 'Müdürlük seçilmeli.';
    if ($data['username'] === '')    $errors[] = 'Kullanıcı adı boş olamaz.';
    if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
                                      $errors[] = 'Geçerli bir e-posta girin.';
    if ($data['full_name'] === '')   $errors[] = 'Ad soyad boş olamaz.';
    if (!in_array($data['role'], ['admin','department_user'])) $errors[] = 'Geçersiz rol.';

    if (!$isEdit || $password !== '') {
        if (strlen($password) < 8)    $errors[] = 'Şifre en az 8 karakter olmalı.';
        if ($password !== $passwordRep) $errors[] = 'Şifreler eşleşmiyor.';
    }

    if (empty($errors)) {
        $dupU = $pdo->prepare('SELECT id FROM users WHERE username = :u AND id != :id LIMIT 1');
        $dupU->execute([':u' => $data['username'], ':id' => $isEdit ? $id : 0]);
        if ($dupU->fetch()) $errors[] = "'{$data['username']}' kullanıcı adı zaten kullanılıyor.";

        $dupE = $pdo->prepare('SELECT id FROM users WHERE email = :e AND id != :id LIMIT 1');
        $dupE->execute([':e' => $data['email'], ':id' => $isEdit ? $id : 0]);
        if ($dupE->fetch()) $errors[] = "'{$data['email']}' e-postası zaten kullanılıyor.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                $oldAudit = [
                    'department_id' => (int) $user['department_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role'],
                    'is_active' => (int) $user['is_active'],
                ];
                $newAudit = [
                    'department_id' => $data['department_id'],
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'full_name' => $data['full_name'],
                    'role' => $data['role'],
                    'is_active' => $data['is_active'],
                ];

                if ($password !== '') {
                    $stmt = $pdo->prepare(
                        "UPDATE users SET department_id=:dept, username=:u, email=:e, full_name=:fn,
                         role=:role, is_active=:active, password_hash=:pw WHERE id=:id"
                    );
                    $stmt->execute([
                        ':dept' => $data['department_id'], ':u' => $data['username'],
                        ':e' => $data['email'], ':fn' => $data['full_name'],
                        ':role' => $data['role'], ':active' => $data['is_active'],
                        ':pw' => password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]),
                        ':id' => $id
                    ]);

                    AuditLog::log($pdo, 'password_reset', 'users', $id, null, [
                        'username' => $data['username'],
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        "UPDATE users SET department_id=:dept, username=:u, email=:e, full_name=:fn,
                         role=:role, is_active=:active WHERE id=:id"
                    );
                    $stmt->execute([
                        ':dept' => $data['department_id'], ':u' => $data['username'],
                        ':e' => $data['email'], ':fn' => $data['full_name'],
                        ':role' => $data['role'], ':active' => $data['is_active'],
                        ':id' => $id
                    ]);
                }
                if (($user['role'] ?? null) !== $data['role']) {
                    AuditLog::log($pdo, 'role_change', 'users', $id, [
                        'role' => $user['role'],
                    ], [
                        'role' => $data['role'],
                    ]);
                }

                AuditLog::log($pdo, 'update', 'users', $id, $oldAudit, $newAudit);
                Flash::success("Kullanıcı güncellendi: {$data['full_name']}");
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO users (department_id, username, email, full_name, role, is_active, password_hash)
                     VALUES (:dept, :u, :e, :fn, :role, :active, :pw)"
                );
                $stmt->execute([
                    ':dept' => $data['department_id'], ':u' => $data['username'],
                    ':e' => $data['email'], ':fn' => $data['full_name'],
                    ':role' => $data['role'], ':active' => $data['is_active'],
                    ':pw' => password_hash($password, PASSWORD_BCRYPT, ['cost'=>12])
                ]);
                $newUserId = (int)$pdo->lastInsertId();
                AuditLog::log($pdo, 'create', 'users', $newUserId, null, [
                    'department_id' => $data['department_id'],
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'full_name' => $data['full_name'],
                    'role' => $data['role'],
                    'is_active' => $data['is_active'],
                ]);
                Flash::success("Kullanıcı oluşturuldu: {$data['full_name']}");
            }

            $pdo->commit();
            header('Location: ' . BASE_PATH . '/public/admin/users.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[SECAP][DB] user_form hatası: ' . $e->getMessage());
            $errors[] = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }

    $user = array_merge($user ?? [], $data);
}

$f = fn(string $k) => htmlspecialchars((string)($user[$k] ?? ''), ENT_QUOTES, 'UTF-8');

require_once APP_ROOT . '/templates/shared/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= BASE_PATH ?>/public/admin/users.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="fw-bold mb-0"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h5>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card" style="max-width:580px;">
    <div class="card-body">
        <form method="POST" novalidate>
            <?= Csrf::field() ?>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">Ad Soyad <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control"
                           value="<?= $f('full_name') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Kullanıcı Adı <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control font-monospace"
                           value="<?= $f('username') ?>" autocomplete="off" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">E-posta <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control"
                           value="<?= $f('email') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Müdürlük <span class="text-danger">*</span></label>
                    <select name="department_id" class="form-select" required>
                        <option value="">Seçiniz…</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>"
                            <?= (int)($user['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Rol <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <option value="department_user" <?= ($user['role'] ?? 'department_user') === 'department_user' ? 'selected' : '' ?>>
                            Müdürlük Yetkilisi
                        </option>
                        <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
                            Sistem Yöneticisi (Admin)
                        </option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">
                        Şifre <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?>
                    </label>
                    <input type="password" name="password" class="form-control"
                           autocomplete="new-password"
                           <?= $isEdit ? '' : 'required' ?>>
                    <?php if ($isEdit): ?>
                    <div class="form-text">Boş bırakılırsa şifre değişmez.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Şifre Tekrar</label>
                    <input type="password" name="password_repeat" class="form-control" autocomplete="new-password">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                               <?= ($user['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Aktif kullanıcı</label>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-<?= $isEdit ? 'check-lg' : 'person-plus' ?> me-1"></i>
                    <?= $isEdit ? 'Güncelle' : 'Oluştur' ?>
                </button>
                <a href="<?= BASE_PATH ?>/public/admin/users.php" class="btn btn-outline-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<?php require_once APP_ROOT . '/templates/shared/footer.php'; ?>
