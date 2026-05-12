<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
Auth::requireSuperAdmin();

$pdo    = Database::getInstance()->getConnection();
$id     = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$editUser   = null;
$errors = [];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([':id' => $id]);
    $editUser = $stmt->fetch();
    if (!$editUser) {
        Flash::error('Kullanıcı bulunamadı.');
        header('Location: ' . BASE_PATH . '/yonetim/kullanicilar');
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
        'role'          => Validator::enum($_POST['role'] ?? '', ['super_admin', 'admin', 'department_user'], 'department_user'),
        'is_active'     => isset($_POST['is_active']) ? 1 : 0,
    ];
    $password    = $_POST['password']        ?? '';
    $passwordRep = $_POST['password_repeat'] ?? '';

    if ($data['department_id'] <= 0) $errors[] = 'Müdürlük seçilmeli.';
    if ($data['username'] === '')    $errors[] = 'Kullanıcı adı boş olamaz.';
    if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
                                      $errors[] = 'Geçerli bir e-posta girin.';
    if ($data['full_name'] === '')   $errors[] = 'Ad soyad boş olamaz.';
    if (!in_array($data['role'], ['super_admin','admin','department_user'])) $errors[] = 'Geçersiz rol.';

    if (!$isEdit || $password !== '') {
        if (strlen($password) < 8)    $errors[] = 'Şifre en az 8 karakter olmalı.';
        if ($password !== $passwordRep) $errors[] = 'Şifreler eşleşmiyor.';
    }

    if (empty($errors)) {
        $dupU = $pdo->prepare('SELECT id FROM users WHERE username = :u AND id != :id AND deleted_at IS NULL LIMIT 1');
        $dupU->execute([':u' => $data['username'], ':id' => $isEdit ? $id : 0]);
        if ($dupU->fetch()) $errors[] = "'{$data['username']}' kullanıcı adı zaten kullanılıyor.";

        $dupE = $pdo->prepare('SELECT id FROM users WHERE email = :e AND id != :id AND deleted_at IS NULL LIMIT 1');
        $dupE->execute([':e' => $data['email'], ':id' => $isEdit ? $id : 0]);
        if ($dupE->fetch()) $errors[] = "'{$data['email']}' e-postası zaten kullanılıyor.";
    }

    $lastSuperAdminBlocked = false;
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($isEdit && $editUser['role'] === 'super_admin'
                && ($data['role'] !== 'super_admin' || $data['is_active'] === 0)) {
                $guardStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM users
                     WHERE role = 'super_admin'
                       AND is_active = 1
                       AND deleted_at IS NULL
                       AND id != :id
                     FOR UPDATE"
                );
                $guardStmt->execute([':id' => $id]);
                if ((int) $guardStmt->fetchColumn() === 0) {
                    $pdo->rollBack();
                    $errors[] = 'Sistemdeki son aktif süper adminin rolü veya aktiflik durumu değiştirilemez.';
                    $lastSuperAdminBlocked = true;
                }
            }

            if (!$lastSuperAdminBlocked && $isEdit) {
                $oldAudit = [
                    'department_id' => (int) $editUser['department_id'],
                    'username' => $editUser['username'],
                    'email' => $editUser['email'],
                    'full_name' => $editUser['full_name'],
                    'role' => $editUser['role'],
                    'is_active' => (int) $editUser['is_active'],
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

                    $prAudit = AuditLog::log($pdo, 'password_reset', 'users', $id, null, [
                        'username' => $data['username'],
                    ]);
                    AuditLog::notify(
                        $pdo,
                        'password_reset',
                        'Sifre sifirlandi',
                        sprintf('%s, "%s" kullanicisinin sifresini sifirladi.', Auth::getFullName(), $data['username']),
                        BASE_PATH . '/yonetim/kullanici-formu?id=' . $id,
                        $prAudit
                    );
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
                if (($editUser['role'] ?? null) !== $data['role']) {
                    $rcAudit = AuditLog::log($pdo, 'role_change', 'users', $id, [
                        'role' => $editUser['role'],
                    ], [
                        'role' => $data['role'],
                    ]);
                    AuditLog::notify(
                        $pdo,
                        'role_change',
                        'Rol degisikligi',
                        sprintf(
                            '%s, "%s" kullanicisinin rolunu "%s" -> "%s" olarak degistirdi.',
                            Auth::getFullName(),
                            $data['username'],
                            (string) $editUser['role'],
                            $data['role']
                        ),
                        BASE_PATH . '/yonetim/kullanici-formu?id=' . $id,
                        $rcAudit
                    );
                }

                AuditLog::log($pdo, 'update', 'users', $id, $oldAudit, $newAudit);
                Flash::success("Kullanıcı güncellendi: {$data['full_name']}");
            } elseif (!$lastSuperAdminBlocked) {
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
                $createAudit = AuditLog::log($pdo, 'create', 'users', $newUserId, null, [
                    'department_id' => $data['department_id'],
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'full_name' => $data['full_name'],
                    'role' => $data['role'],
                    'is_active' => $data['is_active'],
                ]);
                AuditLog::notify(
                    $pdo,
                    'user_created',
                    'Yeni kullanici olusturuldu',
                    sprintf(
                        '%s, yeni kullanici olusturdu: "%s" (%s)',
                        Auth::getFullName(),
                        $data['full_name'],
                        $data['role']
                    ),
                    BASE_PATH . '/yonetim/kullanici-formu?id=' . $newUserId,
                    $createAudit
                );
                Flash::success("Kullanıcı oluşturuldu: {$data['full_name']}");
            }

            if (!$lastSuperAdminBlocked) {
                $pdo->commit();
                header('Location: ' . BASE_PATH . '/yonetim/kullanicilar');
                exit;
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[SECAP][DB] user_form hatası: ' . $e->getMessage());
            $errors[] = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }

    $editUser = array_merge($editUser ?? [], $data);
}

$f = fn(string $k) => htmlspecialchars((string)($editUser[$k] ?? ''), ENT_QUOTES, 'UTF-8');

require_once APP_ROOT . '/uygulama/yerlesim/ust.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= BASE_PATH ?>/yonetim/kullanicilar" class="btn btn-sm btn-outline-secondary">
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

<div class="card">
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
                            <?= (int)($editUser['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Rol <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <option value="department_user" <?= ($editUser['role'] ?? 'department_user') === 'department_user' ? 'selected' : '' ?>>
                            Müdürlük Yetkilisi
                        </option>
                        <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
                            İklim Admin
                        </option>
                        <option value="super_admin" <?= ($editUser['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>
                            Süper Admin
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
                               <?= ($editUser['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Aktif kullanıcı</label>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-<?= $isEdit ? 'check-lg' : 'person-plus' ?> me-1"></i>
                    <?= $isEdit ? 'Güncelle' : 'Oluştur' ?>
                </button>
                <a href="<?= BASE_PATH ?>/yonetim/kullanicilar" class="btn btn-outline-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<?php require_once APP_ROOT . '/uygulama/yerlesim/alt.php'; ?>
