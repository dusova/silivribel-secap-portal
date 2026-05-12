<?php

declare(strict_types=1);

require_once __DIR__ . '/uygulama/baslat.php';
Auth::requireSuperAdmin();

$pdo = Database::getInstance()->getConnection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM system_backups WHERE id = :id AND status = 'success' LIMIT 1");
$stmt->execute([':id' => $id]);
$backup = $stmt->fetch();
if (!$backup) {
    http_response_code(404);
    exit('Yedek bulunamadı.');
}

$path = V2::storagePath((string)$backup['storage_path']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Yedek dosyası bulunamadı.');
}

AuditLog::log($pdo, 'export', 'system_backups', $id, null, ['filename' => $backup['filename']]);

header_remove('X-Powered-By');
header('Content-Type: application/sql; charset=UTF-8');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . addslashes((string)$backup['filename']) . '"');
readfile($path);
exit;
