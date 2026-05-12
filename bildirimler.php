<?php

declare(strict_types=1);

require_once __DIR__ . '/uygulama/baslat.php';
Auth::requireLogin();

$pdo       = Database::getInstance()->getConnection();
$userId    = Auth::getUserId();
$pageTitle = 'Bildirimler';
$activeNav = 'notifications';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['json'])) {
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode([
        'unread' => NotificationService::unreadCount($pdo, (int) $userId),
        'items' => NotificationService::latest($pdo, (int) $userId, 10),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    if (isset($_POST['mark_read_id'])) {
        NotificationService::markRead($pdo, (int) $userId, (int) $_POST['mark_read_id']);
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
        Flash::success('Bildirim okundu olarak işaretlendi.');
    }

    if (isset($_POST['mark_all_read'])) {
        NotificationService::markAllRead($pdo, (int) $userId);
        Flash::success('Tüm bildirimler okundu olarak işaretlendi.');
    }

    header('Location: ' . BASE_PATH . '/bildirimler');
    exit;
}

$labels       = NotificationService::eventLabels();
$filterType   = trim((string) ($_GET['type'] ?? ''));
$filterStatus = trim((string) ($_GET['status'] ?? ''));
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 30;

$where  = ['n.recipient_user_id = :uid'];
$params = [':uid' => (int) $userId];

if ($filterType !== '' && isset($labels[$filterType])) {
    $where[] = 'n.event_key = :event_key';
    $params[':event_key'] = $filterType;
}
if ($filterStatus === '0') {
    $where[] = 'n.is_read = 0';
}
if ($filterStatus === '1') {
    $where[] = 'n.is_read = 1';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications n {$whereSql}");
$countStmt->execute($params);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT n.*
     FROM notifications n
     {$whereSql}
     ORDER BY n.created_at DESC
     LIMIT :lim OFFSET :off"
);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = array_map([NotificationService::class, 'decorate'], $stmt->fetchAll(PDO::FETCH_ASSOC));

$unread = NotificationService::unreadCount($pdo, (int) $userId);

require_once APP_ROOT . '/uygulama/yerlesim/ust.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-bell me-2 text-primary"></i>Bildirimler</h5>
        <small class="text-muted"><?= number_format($total) ?> bildirim · <?= number_format($unread) ?> okunmamış</small>
    </div>
    <?php if ($unread > 0): ?>
    <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="mark_all_read" value="1">
        <button class="btn btn-sm btn-outline-primary">
            <i class="bi bi-check2-all me-1"></i>Tümünü Okundu İşaretle
        </button>
    </form>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="<?= BASE_PATH ?>/bildirimler" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label mb-1 small">Olay</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">Tümü</option>
                    <?php foreach ($labels as $key => $cfg): ?>
                    <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $filterType === $key ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cfg['label'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1 small">Durum</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="" <?= $filterStatus === '' ? 'selected' : '' ?>>Tümü</option>
                    <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Okunmamış</option>
                    <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Okundu</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filtrele</button>
                <a href="<?= BASE_PATH ?>/bildirimler" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="list-group list-group-flush">
        <?php if (empty($items)): ?>
        <div class="text-center text-muted py-4">Bildirim bulunamadı.</div>
        <?php endif; ?>
        <?php foreach ($items as $n): ?>
        <div class="list-group-item d-flex gap-3 align-items-start <?= !$n['is_read'] ? 'bg-primary-subtle' : '' ?>">
            <div class="mt-1">
                <span class="badge bg-<?= htmlspecialchars($n['type_cls'], ENT_QUOTES, 'UTF-8') ?>-subtle text-<?= htmlspecialchars($n['type_cls'], ENT_QUOTES, 'UTF-8') ?> p-2">
                    <i class="bi <?= htmlspecialchars($n['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                </span>
            </div>
            <div class="flex-grow-1" style="min-width:0;">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div style="min-width:0;">
                        <div class="fw-semibold"><?= htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="text-end" style="white-space:nowrap;">
                        <span class="badge bg-<?= htmlspecialchars($n['type_cls'], ENT_QUOTES, 'UTF-8') ?>-subtle text-<?= htmlspecialchars($n['type_cls'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($n['type_label'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <div class="small text-muted"><?= htmlspecialchars($n['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
                <div class="mt-2 d-flex gap-2">
                    <?php if (!empty($n['link'])): ?>
                    <a href="<?= htmlspecialchars($n['link'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Git
                    </a>
                    <?php endif; ?>
                    <?php if (!$n['is_read']): ?>
                    <form method="POST" class="d-inline">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="mark_read_id" value="<?= (int) $n['id'] ?>">
                        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2 me-1"></i>Okundu</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($totalPages > 1):
    $baseQuery = $_GET;
    unset($baseQuery['page']);
    $pageUrl = function (int $p) use ($baseQuery): string {
        return '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
    };
?>
<nav class="mt-3">
    <ul class="pagination pagination-sm mb-0 justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($pageUrl(max(1, $page - 1)), ENT_QUOTES, 'UTF-8') ?>">«</a></li>
        <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($pageUrl($p), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($pageUrl(min($totalPages, $page + 1)), ENT_QUOTES, 'UTF-8') ?>">»</a></li>
    </ul>
</nav>
<?php endif; ?>

<?php require_once APP_ROOT . '/uygulama/yerlesim/alt.php'; ?>
