<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
Auth::requireSuperAdmin();

$pdo       = Database::getInstance()->getConnection();
$pageTitle = 'Kullanıcı Yönetimi';
$activeNav = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    Csrf::check();

    $tid = (int) ($_POST['toggle_user_id'] ?? 0);
    if ($tid > 0 && $tid !== Auth::getUserId()) {
        $stmt = $pdo->prepare('SELECT id, username, role, is_active FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $tid]);
        $targetUser = $stmt->fetch();

        if ($targetUser) {
            $newStatus = (int) !$targetUser['is_active'];
            $pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id')
                ->execute([':is_active' => $newStatus, ':id' => $tid]);

            $scAudit = AuditLog::log($pdo, 'status_change', 'users', $tid, [
                'username' => $targetUser['username'],
                'role' => $targetUser['role'],
                'is_active' => (int) $targetUser['is_active'],
            ], [
                'username' => $targetUser['username'],
                'role' => $targetUser['role'],
                'is_active' => $newStatus,
            ]);
            AuditLog::notify(
                $pdo,
                'status_change',
                'Kullanici durumu degisti',
                sprintf(
                    '%s, "%s" kullanicisini %s.',
                    Auth::getFullName(),
                    $targetUser['username'],
                    $newStatus ? 'aktiflestirdi' : 'pasiflestirdi'
                ),
                BASE_PATH . '/yonetim/kullanici-formu?id=' . $tid,
                $scAudit
            );

            Flash::success('Kullanıcı durumu güncellendi.');
        }
    }
    header('Location: ' . BASE_PATH . '/yonetim/kullanicilar');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soft_delete_id'])) {
    Csrf::check();
    $targetId = (int) $_POST['soft_delete_id'];
    $reason   = Validator::text($_POST['delete_reason'] ?? '', 500);

    if ($targetId === Auth::getUserId()) {
        Flash::error('Kendinizi silemezsiniz.');
    } else {
        $committed = false;
        try {
            $pdo->beginTransaction();

            $snapStmt = $pdo->prepare(
                'SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE'
            );
            $snapStmt->execute([':id' => $targetId]);
            $snapshot = $snapStmt->fetch() ?: [];

            if (empty($snapshot)) {
                $pdo->rollBack();
                Flash::error('Kullanıcı bulunamadı veya zaten silinmiş.');
            } else {
                $blocked = false;
                if ($snapshot['role'] === 'super_admin') {
                    $remainingStmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM users
                         WHERE role = 'super_admin'
                           AND is_active = 1
                           AND deleted_at IS NULL
                           AND id != :id
                         FOR UPDATE"
                    );
                    $remainingStmt->execute([':id' => $targetId]);
                    if ((int) $remainingStmt->fetchColumn() === 0) {
                        $pdo->rollBack();
                        Flash::error('Sistemdeki son aktif süper admin silinemez.');
                        $blocked = true;
                    }
                }

                if (!$blocked) {
                    if (SoftDelete::delete($pdo, 'users', $targetId, $reason, $snapshot)) {
                        $pdo->commit();
                        $committed = true;
                        Flash::success('Kullanıcı çöp kutusuna taşındı.');
                    } else {
                        $pdo->rollBack();
                        Flash::error('Silme işlemi gerçekleştirilemedi.');
                    }
                }
            }
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            Flash::error($e->getMessage());
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('[SECAP][SoftDelete][users] ' . $e->getMessage());
            Flash::error('Silme işlemi sırasında bir hata oluştu.');
        }
        unset($committed);
    }
    header('Location: ' . BASE_PATH . '/yonetim/kullanicilar');
    exit;
}

$users = $pdo->query(
    "SELECT u.id, u.username, u.full_name, u.email, u.role, u.is_active, u.last_login_at,
            d.name AS dept_name
     FROM   users u
     JOIN   departments d ON d.id = u.department_id
     WHERE  u.deleted_at IS NULL
     ORDER  BY u.role, d.name, u.full_name"
)->fetchAll();

require_once APP_ROOT . '/uygulama/yerlesim/ust.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0">Kullanıcılar</h5>
        <small class="text-muted"><?= count($users) ?> kullanıcı</small>
    </div>
    <a href="<?= BASE_PATH ?>/yonetim/kullanici-formu" class="btn btn-success">
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
                        <th>K. Adı</th>
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
                    <tr class="<?= !$u['is_active'] ? 'table-secondary text-muted' : '' ?>" data-user-id="<?= (int) $u['id'] ?>" data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>">
                        <td class="fw-medium"><?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><code><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td class="yazi-kucuk-arti"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="yazi-kucuk-arti">
                            <?= htmlspecialchars($u['dept_name'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="text-center">
                            <?php if ($u['role'] === 'super_admin'): ?>
                                <span class="badge bg-danger-subtle text-danger">Süper Admin</span>
                            <?php elseif ($u['role'] === 'admin'): ?>
                                <span class="badge bg-warning-subtle text-warning">İklim Admin</span>
                            <?php else: ?>
                                <span class="badge bg-primary-subtle text-primary">Müdürlük</span>
                            <?php endif; ?>
                        </td>
                        <td class="yazi-kucuk">
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
                            <a href="<?= BASE_PATH ?>/yonetim/kullanici-formu?id=<?= $u['id'] ?>"
                               class="btn btn-sm btn-outline-secondary py-0 px-2"
                               data-user-id="<?= (int) $u['id'] ?>"
                               aria-label="<?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?> düzenle">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($u['id'] !== Auth::getUserId()): ?>
                            <form method="POST" class="d-inline">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="toggle_user_id" value="<?= (int) $u['id'] ?>">
                                <button type="submit"
                                        class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> py-0 px-2"
                                        title="<?= $u['is_active'] ? 'Devre Dışı Bırak' : 'Aktif Et' ?>"
                                        data-user-id="<?= (int) $u['id'] ?>"
                                        aria-label="<?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?> <?= $u['is_active'] ? 'devre dışı bırak' : 'aktif et' ?>">
                                    <i class="bi bi-<?= $u['is_active'] ? 'pause' : 'play' ?>-circle"></i>
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2"
                                    data-bs-toggle="modal" data-bs-target="#softDeleteModal"
                                    data-sd-id="<?= (int) $u['id'] ?>"
                                    data-sd-label="<?= htmlspecialchars($u['full_name'] . ' (' . $u['username'] . ')', ENT_QUOTES, 'UTF-8') ?>"
                                    title="Çöp Kutusuna Taşı">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$sdFormAction = BASE_PATH . '/yonetim/kullanicilar';
$sdTitle      = 'Kullanıcıyı Çöp Kutusuna Taşı';
$sdLabel      = 'Kullanıcı';
require APP_ROOT . '/uygulama/parcalar/cop-kutusu-modal.php';
?>

<?php require_once APP_ROOT . '/uygulama/yerlesim/alt.php'; ?>
