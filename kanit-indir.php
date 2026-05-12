<?php

declare(strict_types=1);

require_once __DIR__ . '/uygulama/baslat.php';
Auth::requireLogin();

$pdo = Database::getInstance()->getConnection();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

$attachment = EntryAttachment::canAccess($pdo, (int) $id);
if (!$attachment) {
    Auth::denyAccess($pdo, 'entry_attachments', (int) $id, [
        'message' => 'Kanıt dosyasına yetkisiz erişim denemesi.',
    ]);
}

EntryAttachment::sendDownload($attachment);
