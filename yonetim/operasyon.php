<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
Auth::requireSuperAdmin();

$pdo = Database::getInstance()->getConnection();
$pageTitle = 'Operasyon Merkezi';
$activeNav = 'operations';

function op_badge(bool $ok, string $okText = 'Sorunsuz', string $failText = 'Kontrol Gerekli'): string
{
    $cls = $ok ? 'success' : 'danger';
    $text = $ok ? $okText : $failText;
    return '<span class="badge bg-' . $cls . '-subtle text-' . $cls . '">' .
        htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
}

function op_bytes($bytes): string
{
    if ($bytes === null || $bytes === false) {
        return '—';
    }
    $bytes = (float) $bytes;
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2, ',', '.') . ' KB';
    return number_format($bytes, 0, ',', '.') . ' B';
}

$currentUserId = (int) Auth::getUserId();
$userStmt = $pdo->prepare('SELECT email, full_name FROM users WHERE id = :id LIMIT 1');
$userStmt->execute([':id' => $currentUserId]);
$currentUser = $userStmt->fetch() ?: ['email' => '', 'full_name' => Auth::getFullName()];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    if (isset($_POST['send_test_notification'])) {
        $notificationId = NotificationService::create(
            $pdo,
            $currentUserId,
            'generic',
            'Operasyon bildirimi testi',
            'Panel içi bildirim sistemi çalışıyor. Test zamanı: ' . date('d.m.Y H:i:s'),
            BASE_PATH . '/yonetim/operasyon',
            'normal',
            'operations',
            0,
            'operation_test:' . $currentUserId . ':' . date('YmdHis'),
            false
        );
        AuditLog::log($pdo, 'notify', 'notifications', (int) ($notificationId ?: 0), null, [
            'test' => 'panel_notification',
        ]);
        Flash::success('Panel bildirimi oluşturuldu.');
    }

    if (isset($_POST['send_test_email'])) {
        $toEmail = Validator::email($_POST['test_email'] ?? '');
        if ($toEmail === '') {
            Flash::error('Geçerli bir test e-posta adresi girin.');
        } else {
            $toName = Validator::text($_POST['test_name'] ?? '', 150);
            if ($toName === '') {
                $toName = (string) ($currentUser['full_name'] ?? Auth::getFullName());
            }

            $smtpEnabled = defined('SMTP_ENABLED') && SMTP_ENABLED;
            $stmt = $pdo->prepare(
                "INSERT INTO email_queue (recipient_email, recipient_name, subject, body, status, last_error)
                 VALUES (:email, :name, :subject, :body, :status, :err)"
            );
            $stmt->execute([
                ':email' => $toEmail,
                ':name' => mb_substr($toName, 0, 150),
                ':subject' => 'SECAP Portalı SMTP Test E-postası',
                ':body' => "Bu ileti SECAP Portalı Operasyon Merkezi ekranından gönderilen SMTP test e-postasıdır.\n\nTarih: " . date('d.m.Y H:i:s'),
                ':status' => $smtpEnabled ? 'pending' : 'skipped',
                ':err' => $smtpEnabled ? null : 'SMTP_ENABLED kapalı; test kaydı e-posta kuyruğuna gönderim atlandı durumunda yazıldı.',
            ]);
            $emailId = (int) $pdo->lastInsertId();
            if ($smtpEnabled) {
                V2::sendQueuedEmail($pdo, $emailId);
            }
            $statusStmt = $pdo->prepare('SELECT status, last_error FROM email_queue WHERE id = :id LIMIT 1');
            $statusStmt->execute([':id' => $emailId]);
            $emailStatus = $statusStmt->fetch() ?: ['status' => 'unknown', 'last_error' => null];
            AuditLog::log($pdo, 'notify', 'email_queue', $emailId, null, [
                'test' => 'smtp',
                'recipient' => $toEmail,
                'status' => $emailStatus['status'],
            ]);

            if ($emailStatus['status'] === 'sent') {
                Flash::success('SMTP test e-postası gönderildi.');
            } elseif ($emailStatus['status'] === 'skipped') {
                Flash::warning('SMTP kapalı olduğu için test kaydı e-posta kuyruğuna "Gönderim Atlandı" durumuyla yazıldı.');
            } else {
                Flash::error('SMTP test e-postası gönderilemedi: ' . (string) ($emailStatus['last_error'] ?? 'Bilinmeyen hata'));
            }
        }
    }

    header('Location: ' . BASE_PATH . '/yonetim/operasyon');
    exit;
}

$queueStats = $pdo->query(
    "SELECT status, COUNT(*) AS cnt
     FROM email_queue
     GROUP BY status
     ORDER BY status"
)->fetchAll();
$queueByStatus = [];
foreach ($queueStats as $row) {
    $queueByStatus[(string) $row['status']] = (int) $row['cnt'];
}

$lastBackup = $pdo->query(
    "SELECT status, filename, file_size, created_at
     FROM system_backups
     ORDER BY created_at DESC
     LIMIT 1"
)->fetch();
if (!$lastBackup) {
    $lastBackup = [];
}

$duplicateSessions = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM (
        SELECT user_id
        FROM active_sessions
        GROUP BY user_id
        HAVING COUNT(*) > 1
     ) x"
)->fetchColumn();

$storageChecks = [];
foreach ([
    'kanit-dosyalari' => 'Kanıt Dosyaları',
    'sistem-yedekleri' => 'Sistem Yedekleri',
    'gecici-dosyalar' => 'Geçici Dosyalar',
] as $dir => $label) {
    $path = V2::storagePath($dir);
    $storageChecks[] = [
        'label' => 'depolama/' . $dir,
        'title' => $label,
        'path' => $path,
        'exists' => is_dir($path),
        'writable' => is_dir($path) && is_writable($path),
        'free' => @disk_free_space($path) ?: @disk_free_space(APP_STORAGE),
    ];
}

$healthChecks = [
    ['label' => 'PHP Sürümü', 'detail' => PHP_VERSION, 'ok' => version_compare(PHP_VERSION, '7.4.0', '>=')],
    ['label' => 'Görsel İşleme Eklentisi', 'detail' => extension_loaded('gd') ? 'GD eklentisi yüklü' : 'GD eklentisi yüklü değil', 'ok' => extension_loaded('gd')],
    ['label' => 'Veritabanı Bağlantısı', 'detail' => 'Bağlantı aktif', 'ok' => true],
    ['label' => 'Yerel Ağ Adres Kısıtı', 'detail' => AppConfig::allowedHosts() ? implode(', ', AppConfig::allowedHosts()) : 'ALLOWED_HOSTS tanımlı değil', 'ok' => AppConfig::allowedHosts() !== []],
    ['label' => 'Apache Erişim Koruması', 'detail' => is_file(APP_ROOT . '/.htaccess') ? '.htaccess mevcut' : '.htaccess bulunamadı', 'ok' => is_file(APP_ROOT . '/.htaccess')],
    ['label' => 'IIS Erişim Koruması', 'detail' => is_file(APP_ROOT . '/web.config') ? 'web.config mevcut' : 'web.config bulunamadı', 'ok' => is_file(APP_ROOT . '/web.config')],
    ['label' => 'Tek Aktif Oturum Politikası', 'detail' => $duplicateSessions === 0 ? 'Kullanıcı başına tek aktif oturum' : $duplicateSessions . ' kullanıcıda çoklu oturum var', 'ok' => $duplicateSessions === 0],
    ['label' => 'SMTP E-posta Servisi', 'detail' => (defined('SMTP_ENABLED') && SMTP_ENABLED) ? SMTP_HOST . ':' . SMTP_PORT : 'Devre dışı', 'ok' => defined('SMTP_ENABLED') && SMTP_ENABLED && SMTP_HOST !== ''],
];

$statusText = [
    'sent' => 'Gönderildi',
    'failed' => 'Hatalı',
    'pending' => 'Bekliyor',
    'skipped' => 'Gönderim Atlandı',
];

require_once APP_ROOT . '/uygulama/yerlesim/ust.php';
?>

<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-activity me-2 text-primary"></i>Operasyon Merkezi</h5>
        <div class="text-muted small">Belediye yerel ağında çalışan portalın sistem sağlığını, bildirim kanallarını, e-posta servisini ve yedek durumunu buradan izleyin.</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="istatistik-karti bg-white">
            <div class="ist-etiket">E-posta Kuyruğu</div>
            <div class="ist-deger fs-2"><?= array_sum($queueByStatus) ?></div>
            <div class="small text-muted">
                Gönderildi: <?= (int)($queueByStatus['sent'] ?? 0) ?> · Hatalı: <?= (int)($queueByStatus['failed'] ?? 0) ?> · Bekliyor: <?= (int)($queueByStatus['pending'] ?? 0) ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="istatistik-karti bg-white">
            <div class="ist-etiket">Son Yedek</div>
            <div class="fs-6 fw-bold text-truncate"><?= htmlspecialchars((string)($lastBackup['filename'] ?? 'Henüz yok'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="small text-muted">
                <?= htmlspecialchars((string)($lastBackup['created_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                · <?= op_bytes($lastBackup['file_size'] ?? null) ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="istatistik-karti bg-white">
            <div class="ist-etiket">SMTP E-posta Servisi</div>
            <div class="fs-5 fw-bold"><?= (defined('SMTP_ENABLED') && SMTP_ENABLED) ? 'Etkin' : 'Devre Dışı' ?></div>
            <div class="small text-muted"><?= htmlspecialchars((defined('SMTP_HOST') ? SMTP_HOST : '') ?: 'Sunucu tanımlı değil', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-heart-pulse me-2 text-success"></i>Sistem Sağlığı Kontrolü</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Kontrol</th><th>Detay</th><th class="text-end">Durum</th></tr></thead>
                    <tbody>
                    <?php foreach ($healthChecks as $check): ?>
                    <tr>
                        <td class="fw-medium"><?= htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="small text-muted"><?= htmlspecialchars((string)$check['detail'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-end"><?= op_badge((bool)$check['ok']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-folder-check me-2 text-primary"></i>Depolama Klasörleri</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Klasör</th><th>Kullanım Amacı</th><th>Boş Alan</th><th class="text-end">Durum</th></tr></thead>
                    <tbody>
                    <?php foreach ($storageChecks as $check): ?>
                    <tr>
                        <td>
                            <div class="fw-medium"><?= htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="small text-muted text-truncate" style="max-width:420px;"><?= htmlspecialchars($check['path'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td><?= htmlspecialchars($check['title'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= op_bytes($check['free']) ?></td>
                        <td class="text-end"><?= op_badge($check['exists'] && $check['writable'], 'Yazılabilir', 'Sorun Var') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-bell me-2 text-warning"></i>Panel Bildirimi Testi</div>
            <div class="card-body">
                <p class="small text-muted mb-3">Giriş yapan Süper Admin hesabı için panel içi test bildirimi oluşturur.</p>
                <form method="POST">
                    <?= Csrf::field() ?>
                    <button class="btn btn-outline-primary" name="send_test_notification" value="1">
                        <i class="bi bi-send-check me-1"></i>Panel Bildirimi Gönder
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-envelope-check me-2 text-success"></i>SMTP E-posta Testi</div>
            <div class="card-body">
                <form method="POST">
                    <?= Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Test Alıcısı E-posta Adresi</label>
                        <input type="email" name="test_email" class="form-control"
                               value="<?= htmlspecialchars((string)($currentUser['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alıcı Adı Soyadı</label>
                        <input type="text" name="test_name" class="form-control"
                               value="<?= htmlspecialchars((string)($currentUser['full_name'] ?? Auth::getFullName()), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <button class="btn btn-success" name="send_test_email" value="1">
                        <i class="bi bi-envelope-paper me-1"></i>SMTP Test E-postası Gönder
                    </button>
                </form>
                <div class="small text-muted mt-3">
                    SMTP devre dışıysa kayıt e-posta kuyruğuna <code><?= htmlspecialchars($statusText['skipped'], ENT_QUOTES, 'UTF-8') ?></code> durumuyla yazılır; panel içi bildirimler çalışmaya devam eder.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/uygulama/yerlesim/alt.php'; ?>
