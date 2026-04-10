<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireAdmin();

$pdo       = Database::getInstance()->getConnection();
$pageTitle = 'Kullanıcı Yönetimi';
$activeNav = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    Csrf::check();

    $tid = (int) ($_POST['toggle_user_id'] ?? 0);
    if ($tid > 0 && $tid !== Auth::getUserId()) { // Kendini devre dışı bırakmasın
        $stmt = $pdo->prepare('SELECT id, username, role, is_active FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $tid]);
        $targetUser = $stmt->fetch();

        if ($targetUser) {
            $newStatus = (int) !$targetUser['is_active'];
            $pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id')
                ->execute([':is_active' => $newStatus, ':id' => $tid]);

            AuditLog::log($pdo, 'status_change', 'users', $tid, [
                'username' => $targetUser['username'],
                'role' => $targetUser['role'],
                'is_active' => (int) $targetUser['is_active'],
            ], [
                'username' => $targetUser['username'],
                'role' => $targetUser['role'],
                'is_active' => $newStatus,
            ]);

            Flash::success('Kullanıcı durumu güncellendi.');
        }
    }
    header('Location: ' . BASE_PATH . '/public/admin/users.php');
    exit;
}

$users = $pdo->query(
    "SELECT u.id, u.username, u.full_name, u.email, u.role, u.is_active, u.last_login_at,
            d.name AS dept_name
     FROM   users u
     JOIN   departments d ON d.id = u.department_id
     ORDER  BY u.role, d.name, u.full_name"
)->fetchAll();

require_once APP_ROOT . '/templates/shared/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0">Kullanıcılar</h5>
        <small class="text-muted"><?= count($users) ?> kullanıcı</small>
    </div>
    <a href="<?= BASE_PATH ?>/public/admin/user_form.php" class="btn btn-success">
        <i class="bi bi-person-plus me-1"></i>Yeni Kullanıcı
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ad Soyad</th>
                        <th>Kullanıcı Adı</th>
                        <th>E-posta</th>
                        <th>Müdürlük</th>
                        <th class="text-center">Rol</th>
                        <th>Son Giriş</th>
                        <th class="text-center">Durum</th>
                        <th class="text-end">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr class="<?= !$u['is_active'] ? 'table-secondary text-muted' : '' ?>">
                        <td class="fw-medium"><?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><code><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td class="text-sm-plus"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-sm-plus">
                            <?= htmlspecialchars($u['dept_name'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="text-center">
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge bg-danger-subtle text-danger">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-primary-subtle text-primary">Müdürlük</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm">
                            <?= $u['last_login_at']
                                ? date('d.m.Y H:i', strtotime($u['last_login_at']))
                                : '<span class="text-muted">Hiç giriş yapılmadı</span>' ?>
                        </td>
                        <td class="text-center">
                            <?php if ($u['is_active']): ?>
                                <span class="badge bg-success-subtle text-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= BASE_PATH ?>/public/admin/user_form.php?id=<?= $u['id'] ?>"
                               class="btn btn-sm btn-outline-secondary py-0 px-2">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($u['id'] !== Auth::getUserId()): ?>
                            <form method="POST" class="d-inline">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="toggle_user_id" value="<?= (int) $u['id'] ?>">
                                <button type="submit"
                                        class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> py-0 px-2"
                                        title="<?= $u['is_active'] ? 'Devre Dışı Bırak' : 'Aktif Et' ?>">
                                    <i class="bi bi-<?= $u['is_active'] ? 'pause' : 'play' ?>-circle"></i>
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

<?php require_once APP_ROOT . '/templates/shared/footer.php'; ?>
