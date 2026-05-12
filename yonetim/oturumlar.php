<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
Auth::requireSuperAdmin();

$pdo       = Database::getInstance()->getConnection();
$pageTitle = 'Aktif Oturumlar';
$activeNav = 'sessions';

$mySessionHash = Auth::sessionIdHash();
if ($mySessionHash === '') {
    $mySessionHash = null;
}

try {
    $pdo->exec("DELETE FROM active_sessions WHERE last_activity_at < NOW() - INTERVAL 8 HOUR");
    $pdo->exec(
        "DELETE s1
         FROM active_sessions s1
         JOIN active_sessions s2
           ON s1.user_id = s2.user_id
          AND (
               s1.last_activity_at < s2.last_activity_at
               OR (s1.last_activity_at = s2.last_activity_at AND s1.session_id < s2.session_id)
          )"
    );
} catch (PDOException $e) {
    error_log('[SECAP][SESSIONS] stale cleanup failed: ' . $e->getMessage());
}

$stmt = $pdo->query(
    "SELECT s.session_id, s.user_id, s.ip_address, s.user_agent, s.last_activity_at, s.created_at,
            u.full_name, u.username, u.role,
            d.name AS dept_name
     FROM active_sessions s
     LEFT JOIN users u       ON u.id = s.user_id
     LEFT JOIN departments d ON d.id = u.department_id
     WHERE (u.deleted_at IS NULL OR u.id IS NULL)
       AND s.last_activity_at >= NOW() - INTERVAL 8 HOUR
     ORDER BY s.last_activity_at DESC"
);
$sessions = $stmt->fetchAll();

$roleLabels = [
    'super_admin'     => ['label' => 'Süper Admin', 'cls' => 'danger'],
    'admin'           => ['label' => 'İklim Admin', 'cls' => 'warning'],
    'department_user' => ['label' => 'Müdürlük',    'cls' => 'info'],
];

$threshold = 15 * 60;
$now = time();

function ua_label(?string $ua): string
{
    if (!$ua) return 'Bilinmiyor';
    $ua = (string) $ua;
    if (stripos($ua, 'edg/') !== false)     return 'Edge';
    if (stripos($ua, 'chrome') !== false)   return 'Chrome';
    if (stripos($ua, 'firefox') !== false)  return 'Firefox';
    if (stripos($ua, 'safari') !== false)   return 'Safari';
    if (stripos($ua, 'postman') !== false)  return 'Postman';
    return mb_substr($ua, 0, 40);
}

require_once APP_ROOT . '/uygulama/yerlesim/ust.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-broadcast me-2 text-primary"></i>Aktif Oturumlar</h5>
        <small class="text-muted"><?= count($sessions) ?> oturum · Her kullanıcı için yalnızca en güncel oturum tutulur.</small>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 yazi-orta align-middle">
            <thead class="table-light">
                <tr>
                    <th>Kullanıcı</th>
                    <th>Rol</th>
                    <th>Müdürlük</th>
                    <th>IP</th>
                    <th>Tarayıcı</th>
                    <th>İlk Giriş</th>
                    <th>Son Aktivite</th>
                    <th>Durum</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sessions)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Aktif oturum bulunmuyor.</td></tr>
                <?php endif; ?>
                <?php foreach ($sessions as $s):
                    $role = $s['role'] ?? '';
                    $roleCfg = $roleLabels[$role] ?? ['label' => '—', 'cls' => 'secondary'];
                    $lastTs  = $s['last_activity_at'] ? strtotime($s['last_activity_at']) : null;
                    $isStale = $lastTs !== null && ($now - $lastTs) > $threshold;
                    $isMe    = $mySessionHash !== null && $s['session_id'] === $mySessionHash;
                ?>
                <tr class="<?= $isMe ? 'table-info' : '' ?>">
                    <td>
                        <div class="fw-medium"><?= htmlspecialchars((string) ($s['full_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
                        <small class="text-muted">@<?= htmlspecialchars((string) ($s['username'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></small>
                    </td>
                    <td><span class="badge bg-<?= htmlspecialchars($roleCfg['cls'], ENT_QUOTES, 'UTF-8') ?>-subtle text-<?= htmlspecialchars($roleCfg['cls'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($roleCfg['label'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><small><?= htmlspecialchars((string) ($s['dept_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                    <td><code class="small"><?= htmlspecialchars((string) ($s['ip_address'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td>
                        <span class="fw-medium"><?= htmlspecialchars(ua_label($s['user_agent']), ENT_QUOTES, 'UTF-8') ?></span>
                        <div class="small text-muted text-truncate" style="max-width:220px;" title="<?= htmlspecialchars((string) ($s['user_agent'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) ($s['user_agent'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </td>
                    <td><small><?= htmlspecialchars((string) ($s['created_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></small></td>
                    <td><small><?= htmlspecialchars((string) ($s['last_activity_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></small></td>
                    <td>
                        <?php if ($isMe): ?>
                            <span class="badge bg-primary">Bu oturum (sizsiniz)</span>
                        <?php elseif ($isStale): ?>
                            <span class="badge bg-secondary">Uyku modu</span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success">Aktif</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once APP_ROOT . '/uygulama/yerlesim/alt.php'; ?>
