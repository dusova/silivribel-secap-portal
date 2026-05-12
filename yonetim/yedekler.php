<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
Auth::requireSuperAdmin();

$pdo = Database::getInstance()->getConnection();
$pageTitle = 'Sistem Yedekleri';
$activeNav = 'backups';

function secap_quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function secap_sql_literal(PDO $pdo, $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    $quoted = $pdo->quote((string) $value);
    if ($quoted === false) {
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    return $quoted;
}

function secap_write_database_dump(PDO $pdo, string $databaseName, string $target): array
{
    $handle = fopen($target, 'wb');
    if (!is_resource($handle)) {
        return [1, 'Yedek dosyası oluşturulamadı.'];
    }

    try {
        fwrite($handle, "-- SECAP Portalı veritabanı yedeği\n");
        fwrite($handle, '-- Oluşturma zamanı: ' . date('Y-m-d H:i:s') . "\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tableStmt = $pdo->prepare(
            "SELECT TABLE_NAME
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = :schema_name
               AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME"
        );
        $tableStmt->execute([':schema_name' => $databaseName]);
        $tables = $tableStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $tableName = (string) $table;
            $quotedTable = secap_quote_identifier($tableName);
            fwrite($handle, "\n-- Tablo: {$tableName}\n");
            fwrite($handle, "DROP TABLE IF EXISTS {$quotedTable};\n");

            $createStmt = $pdo->query('SHOW CREATE TABLE ' . $quotedTable);
            $createRow = $createStmt ? $createStmt->fetch(PDO::FETCH_ASSOC) : false;
            if (!$createRow) {
                throw new RuntimeException($tableName . ' tablosunun şeması okunamadı.');
            }
            $createSql = (string) ($createRow['Create Table'] ?? array_values($createRow)[1] ?? '');
            if ($createSql === '') {
                throw new RuntimeException($tableName . ' tablosu için CREATE TABLE çıktısı boş geldi.');
            }
            fwrite($handle, $createSql . ";\n\n");

            $dataStmt = $pdo->query('SELECT * FROM ' . $quotedTable);
            if (!$dataStmt) {
                throw new RuntimeException($tableName . ' tablosunun verileri okunamadı.');
            }

            while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_map('secap_quote_identifier', array_keys($row));
                $values = array_map(
                    static fn($value): string => secap_sql_literal($pdo, $value),
                    array_values($row)
                );
                fwrite(
                    $handle,
                    'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n"
                );
            }
        }

        fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
        return [0, 'Yedek başarıyla oluşturuldu.'];
    } catch (Throwable $e) {
        fclose($handle);
        return [1, $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    if (isset($_POST['create_backup'])) {
        $backupDir = V2::storagePath('sistem-yedekleri');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        $envConfig = require APP_ROOT . '/ortam.php';
        $db = (string)($envConfig['DB_NAME'] ?? 'secap_portal');

        $filename = 'secap_backup_' . date('Ymd_His') . '.sql';
        $target = $backupDir . '/' . $filename;
        $storagePath = 'sistem-yedekleri/' . $filename;
        $status = 'failed';
        $message = null;

        [$exitCode, $message] = secap_write_database_dump($pdo, $db, $target);
        if ($exitCode === 0 && is_file($target) && filesize($target) > 0) {
            chmod($target, 0664);
            $status = 'success';
            $message = $message ?: 'Yedek başarıyla oluşturuldu.';
        } else {
            $message = $message ?: 'Veritabanı yedeği oluşturulamadı.';
        }

        $stmt = $pdo->prepare(
            "INSERT INTO system_backups (filename, storage_path, file_size, status, message, created_by)
             VALUES (:filename, :storage, :size, :status, :message, :uid)"
        );
        $stmt->execute([
            ':filename' => $filename,
            ':storage' => $storagePath,
            ':size' => is_file($target) ? filesize($target) : null,
            ':status' => $status,
            ':message' => $message,
            ':uid' => Auth::getUserId(),
        ]);
        $backupId = (int) $pdo->lastInsertId();
        AuditLog::log($pdo, 'create', 'system_backups', $backupId, null, [
            'filename' => $filename,
            'status' => $status,
            'message' => $message,
        ]);

        if ($status === 'success') {
            Flash::success('Yedek oluşturuldu.');
        } else {
            NotificationService::notifySuperAdmins(
                $pdo,
                'backup_failed',
                'Yedekleme hatası',
                $message ?: 'Sistem yedeği oluşturulamadı.',
                BASE_PATH . '/yonetim/yedekler',
                'critical',
                'system_backups',
                $backupId,
                'backup_failed:' . $backupId,
                false
            );
            Flash::error('Yedek oluşturulamadı: ' . ($message ?: 'Bilinmeyen hata'));
        }

        header('Location: ' . BASE_PATH . '/yonetim/yedekler');
        exit;
    }
}

$backups = $pdo->query(
    "SELECT b.*, u.full_name AS created_by_name
     FROM system_backups b
     LEFT JOIN users u ON u.id = b.created_by
     ORDER BY b.created_at DESC
     LIMIT 80"
)->fetchAll();

$lastSuccess = $pdo->query("SELECT MAX(created_at) FROM system_backups WHERE status = 'success'")->fetchColumn();
$failedWeek = (int)$pdo->query("SELECT COUNT(*) FROM system_backups WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

require_once APP_ROOT . '/uygulama/yerlesim/ust.php';
?>

<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-database-down me-2 text-primary"></i>Sistem Yedekleri</h5>
        <div class="text-muted small">SQL yedekleri web dışına kapalı depolama alanında tutulur ve yalnızca Süper Admin indirebilir.</div>
    </div>
    <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="create_backup" value="1">
        <button class="btn btn-success" onclick="return confirm('Yeni veritabanı yedeği oluşturulsun mu?')">
            <i class="bi bi-plus-lg me-1"></i>Yedek Oluştur
        </button>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="istatistik-karti bg-white"><div class="ist-etiket">Son Başarılı Yedek</div><div class="fs-5 fw-bold"><?= htmlspecialchars((string)($lastSuccess ?: 'Yok'), ENT_QUOTES, 'UTF-8') ?></div></div></div>
    <div class="col-md-4"><div class="istatistik-karti bg-white"><div class="ist-etiket">Son 7 Gün Hata</div><div class="ist-deger fs-2"><?= $failedWeek ?></div></div></div>
    <div class="col-md-4"><div class="istatistik-karti bg-white"><div class="ist-etiket">Kayıt Sayısı</div><div class="ist-deger fs-2"><?= count($backups) ?></div></div></div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-archive me-2"></i>Yedek Geçmişi</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Dosya</th><th>Boyut</th><th>Durum</th><th>Oluşturan</th><th>Tarih</th><th>Mesaj</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($backups)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Henüz yedek kaydı yok.</td></tr>
            <?php endif; ?>
            <?php foreach ($backups as $b): ?>
            <tr>
                <td class="font-monospace"><?= htmlspecialchars($b['filename'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $b['file_size'] ? number_format((float)$b['file_size'] / 1024 / 1024, 2, ',', '.') . ' MB' : '—' ?></td>
                <td><?= $b['status'] === 'success'
                    ? '<span class="badge bg-success-subtle text-success">Başarılı</span>'
                    : '<span class="badge bg-danger-subtle text-danger">Hatalı</span>' ?></td>
                <td><?= htmlspecialchars((string)($b['created_by_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$b['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="small text-muted"><?= htmlspecialchars((string)($b['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-end">
                    <?php if ($b['status'] === 'success'): ?>
                    <a href="<?= BASE_PATH ?>/yedek-indir?id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-download me-1"></i>İndir
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once APP_ROOT . '/uygulama/yerlesim/alt.php'; ?>
