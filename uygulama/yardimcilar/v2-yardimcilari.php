<?php

declare(strict_types=1);

final class V2
{
    private const STORAGE_ALIASES = [
        'attachments' => 'kanit-dosyalari',
        'backups' => 'sistem-yedekleri',
        'tmp' => 'gecici-dosyalar',
    ];

    public const ENTRY_STATUSES = [
        'submitted'      => ['label' => 'Onay Bekliyor',   'class' => 'bg-warning-subtle text-warning',     'icon' => 'bi-hourglass-split'],
        'needs_revision' => ['label' => 'Düzeltme İstendi','class' => 'bg-info-subtle text-info',           'icon' => 'bi-arrow-repeat'],
        'approved'       => ['label' => 'Onaylı',          'class' => 'bg-success-subtle text-success',     'icon' => 'bi-check-circle'],
        'rejected'       => ['label' => 'Reddedildi',      'class' => 'bg-danger-subtle text-danger',       'icon' => 'bi-x-circle'],
    ];

    public static function entryStatusBadge(?string $status): string
    {
        $status = $status ?: 'submitted';
        $cfg = self::ENTRY_STATUSES[$status] ?? self::ENTRY_STATUSES['submitted'];
        return '<span class="badge ' . $cfg['class'] . '"><i class="bi ' . $cfg['icon'] . ' me-1"></i>' .
            htmlspecialchars($cfg['label'], ENT_QUOTES, 'UTF-8') . '</span>';
    }

    public static function storagePath(string $child = ''): string
    {
        $base = APP_ROOT . '/depolama';
        $child = trim(str_replace('\\', '/', $child), '/');
        if ($child === '') {
            return $base;
        }

        $parts = explode('/', $child);
        if (isset($parts[0]) && in_array($parts[0], ['storage', 'depolama'], true)) {
            array_shift($parts);
        }
        if ($parts === []) {
            return $base;
        }
        if (isset(self::STORAGE_ALIASES[$parts[0]])) {
            $parts[0] = self::STORAGE_ALIASES[$parts[0]];
        }

        return $base . '/' . implode('/', $parts);
    }

    public static function notifyAdmins(PDO $pdo, string $title, string $message, ?string $link = null): void
    {
        NotificationService::notifyAdmins($pdo, 'generic', $title, $message, $link, 'normal', null, null, null, true);
    }

    public static function notifyUser(PDO $pdo, int $userId, string $title, string $message, ?string $link = null): void
    {
        NotificationService::notifyUser($pdo, $userId, 'generic', $title, $message, $link, 'normal', null, null, null, true);
    }

    public static function queueEmail(PDO $pdo, string $email, string $name, string $subject, string $body): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $smtpEnabled = defined('SMTP_ENABLED') && SMTP_ENABLED;
        $status = $smtpEnabled ? 'pending' : 'skipped';
        $stmt = $pdo->prepare(
            "INSERT INTO email_queue (recipient_email, recipient_name, subject, body, status, last_error)
             VALUES (:email, :name, :subject, :body, :status, :err)"
        );
        $stmt->execute([
            ':email' => $email,
            ':name' => mb_substr($name, 0, 150),
            ':subject' => mb_substr($subject, 0, 220),
            ':body' => $body,
            ':status' => $status,
            ':err' => $smtpEnabled ? null : 'SMTP devre dışı; yalnızca panel içi bildirim oluşturuldu.',
        ]);
        $emailId = (int) $pdo->lastInsertId();
        if ($smtpEnabled && $emailId > 0) {
            self::sendQueuedEmail($pdo, $emailId);
        }
    }

    public static function sendQueuedEmails(PDO $pdo, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $pdo->prepare(
            "SELECT id FROM email_queue
             WHERE status IN ('pending','failed') AND attempts < 3
             ORDER BY queued_at ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $sent = 0;
        $failed = 0;
        foreach ($stmt->fetchAll() as $row) {
            if (self::sendQueuedEmail($pdo, (int) $row['id'])) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    public static function sendQueuedEmail(PDO $pdo, int $emailId): bool
    {
        if (!defined('SMTP_ENABLED') || !SMTP_ENABLED) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT * FROM email_queue WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $emailId]);
        $email = $stmt->fetch();
        if (!$email) {
            return false;
        }

        try {
            self::sendSmtp(
                (string) $email['recipient_email'],
                (string) ($email['recipient_name'] ?? ''),
                (string) $email['subject'],
                (string) $email['body']
            );
            $pdo->prepare(
                "UPDATE email_queue
                 SET status = 'sent', attempts = attempts + 1, sent_at = NOW(), last_error = NULL
                 WHERE id = :id"
            )->execute([':id' => $emailId]);
            return true;
        } catch (Throwable $e) {
            error_log('[SECAP][SMTP] ' . $e->getMessage());
            $pdo->prepare(
                "UPDATE email_queue
                 SET status = 'failed', attempts = attempts + 1, last_error = :err
                 WHERE id = :id"
            )->execute([
                ':id' => $emailId,
                ':err' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            return false;
        }
    }

    private static function sendSmtp(string $toEmail, string $toName, string $subject, string $body): void
    {
        $host = defined('SMTP_HOST') ? trim((string) SMTP_HOST) : '';
        if ($host === '') {
            throw new RuntimeException('SMTP_HOST boş.');
        }
        $port = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;
        $secure = strtolower(defined('SMTP_SECURE') ? (string) SMTP_SECURE : 'tls');
        $remote = $secure === 'ssl' ? 'ssl://' . $host . ':' . $port : $host . ':' . $port;

        $stream = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!is_resource($stream)) {
            throw new RuntimeException("SMTP bağlantısı kurulamadı: {$errstr} ({$errno})");
        }
        stream_set_timeout($stream, 20);

        try {
            self::smtpExpect($stream, [220]);
            self::smtpCommand($stream, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);

            if ($secure === 'tls') {
                self::smtpCommand($stream, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP TLS başlatılamadı.');
                }
                self::smtpCommand($stream, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
            }

            if (defined('SMTP_USER') && SMTP_USER !== '') {
                self::smtpCommand($stream, 'AUTH LOGIN', [334]);
                self::smtpCommand($stream, base64_encode((string) SMTP_USER), [334]);
                self::smtpCommand($stream, base64_encode((string) SMTP_PASS), [235]);
            }

            $fromEmail = defined('SMTP_FROM_EMAIL') ? (string) SMTP_FROM_EMAIL : 'secap@localhost';
            $fromName = defined('SMTP_FROM_NAME') ? (string) SMTP_FROM_NAME : 'SECAP Portal';
            self::smtpCommand($stream, 'MAIL FROM:<' . self::smtpAddress($fromEmail) . '>', [250]);
            self::smtpCommand($stream, 'RCPT TO:<' . self::smtpAddress($toEmail) . '>', [250, 251]);
            self::smtpCommand($stream, 'DATA', [354]);

            $headers = [
                'From: ' . self::mimeHeader($fromName) . ' <' . self::smtpAddress($fromEmail) . '>',
                'To: ' . self::mimeHeader($toName !== '' ? $toName : $toEmail) . ' <' . self::smtpAddress($toEmail) . '>',
                'Subject: ' . self::mimeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'Date: ' . date(DATE_RFC2822),
            ];
            $message = implode("\r\n", $headers) . "\r\n\r\n" . self::dotStuff($body) . "\r\n.";
            fwrite($stream, $message . "\r\n");
            self::smtpExpect($stream, [250]);
            self::smtpCommand($stream, 'QUIT', [221]);
        } finally {
            fclose($stream);
        }
    }

    private static function smtpCommand($stream, string $command, array $okCodes): string
    {
        fwrite($stream, $command . "\r\n");
        return self::smtpExpect($stream, $okCodes);
    }

    private static function smtpExpect($stream, array $okCodes): string
    {
        $response = '';
        while (($line = fgets($stream, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^(\d{3})\s/', $line, $m)) {
                $code = (int) $m[1];
                if (!in_array($code, $okCodes, true)) {
                    throw new RuntimeException('SMTP beklenmeyen yanıt: ' . trim($response));
                }
                return $response;
            }
        }
        throw new RuntimeException('SMTP yanıtı alınamadı.');
    }

    private static function mimeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function smtpAddress(string $email): string
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Geçersiz e-posta adresi: ' . $email);
        }
        return $email;
    }

    private static function dotStuff(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $body);
        foreach ($lines as &$line) {
            if (isset($line[0]) && $line[0] === '.') {
                $line = '.' . $line;
            }
        }
        unset($line);
        return implode("\r\n", $lines);
    }
}

final class EntryAttachment
{
    private const MAX_BYTES = 12582912;
    private const EXT_MIME = [
        'pdf'  => ['application/pdf'],
        'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'csv'  => ['text/csv', 'text/plain', 'application/vnd.ms-excel'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'doc'  => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    ];

    public static function uploadedCount(string $field): int
    {
        if (empty($_FILES[$field]) || !isset($_FILES[$field]['name'])) {
            return 0;
        }
        $names = is_array($_FILES[$field]['name']) ? $_FILES[$field]['name'] : [$_FILES[$field]['name']];
        $errors = is_array($_FILES[$field]['error']) ? $_FILES[$field]['error'] : [$_FILES[$field]['error']];
        $count = 0;
        foreach ($names as $i => $name) {
            if ((string) $name !== '' && (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $count++;
            }
        }
        return $count;
    }

    public static function validateUploadSet(string $field): array
    {
        $errors = [];
        foreach (self::normalizeFiles($field) as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = $file['name'] . ': dosya yüklenemedi.';
                continue;
            }
            if ($file['size'] <= 0 || $file['size'] > self::MAX_BYTES) {
                $errors[] = $file['name'] . ': dosya boyutu 12 MB sınırını aşamaz.';
            }
            $ext = self::extension($file['name']);
            if (!isset(self::EXT_MIME[$ext])) {
                $errors[] = $file['name'] . ': izin verilmeyen dosya türü.';
                continue;
            }
            $mime = self::detectMime($file['tmp_name']);
            if ($mime !== '' && !in_array($mime, self::EXT_MIME[$ext], true)) {
                $errors[] = $file['name'] . ': dosya içeriği uzantı ile uyumlu değil.';
            }
        }
        return $errors;
    }

    public static function saveUploaded(PDO $pdo, int $entryId, string $field = 'evidence_files'): int
    {
        $saved = 0;
        $storageDir = 'kanit-dosyalari/' . date('Y');
        $dir = V2::storagePath($storageDir);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        foreach (self::normalizeFiles($field) as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }
            $ext = self::extension($file['name']);
            if (!isset(self::EXT_MIME[$ext])) {
                continue;
            }
            $mime = self::detectMime($file['tmp_name']) ?: (string) ($file['type'] ?? 'application/octet-stream');
            if (!in_array($mime, self::EXT_MIME[$ext], true)) {
                continue;
            }
            $hash = hash_file('sha256', $file['tmp_name']);
            $storageName = $entryId . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
            $target = $dir . '/' . $storageName;
            if (!move_uploaded_file($file['tmp_name'], $target)) {
                continue;
            }
            chmod($target, 0664);
            $stmt = $pdo->prepare(
                "INSERT INTO entry_attachments
                    (data_entry_id, uploaded_by, original_name, storage_name, mime_type, file_size, file_hash)
                 VALUES (:eid, :uid, :original, :storage, :mime, :size, :hash)"
            );
            $stmt->execute([
                ':eid' => $entryId,
                ':uid' => Auth::getUserId(),
                ':original' => mb_substr(self::safeOriginalName($file['name']), 0, 255),
                ':storage' => $storageDir . '/' . $storageName,
                ':mime' => mb_substr($mime, 0, 120),
                ':size' => (int) $file['size'],
                ':hash' => $hash,
            ]);
            $saved++;
        }

        return $saved;
    }

    public static function countForEntry(PDO $pdo, int $entryId): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM entry_attachments WHERE data_entry_id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => $entryId]);
        return (int) $stmt->fetchColumn();
    }

    public static function listForEntry(PDO $pdo, int $entryId): array
    {
        $stmt = $pdo->prepare(
            "SELECT ea.*, u.full_name AS uploaded_by_name
             FROM entry_attachments ea
             LEFT JOIN users u ON u.id = ea.uploaded_by
             WHERE ea.data_entry_id = :id AND ea.deleted_at IS NULL
             ORDER BY ea.created_at DESC"
        );
        $stmt->execute([':id' => $entryId]);
        return $stmt->fetchAll();
    }

    public static function canAccess(PDO $pdo, int $attachmentId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT ea.*, de.action_id, de.department_id, de.entered_by
             FROM entry_attachments ea
             JOIN data_entries de ON de.id = ea.data_entry_id
             WHERE ea.id = :id AND ea.deleted_at IS NULL AND de.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([':id' => $attachmentId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if (Auth::isAdmin() || (int) $row['entered_by'] === Auth::getUserId() || Auth::canAccessAction($pdo, (int) $row['action_id'])) {
            return $row;
        }
        return null;
    }

    public static function sendDownload(array $attachment): void
    {
        $path = V2::storagePath((string) $attachment['storage_name']);
        if (!is_file($path)) {
            http_response_code(404);
            exit('Dosya bulunamadı.');
        }
        header_remove('X-Powered-By');
        header('Content-Type: ' . $attachment['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . addslashes((string) $attachment['original_name']) . '"');
        readfile($path);
        exit;
    }

    private static function normalizeFiles(string $field): array
    {
        if (empty($_FILES[$field]) || !isset($_FILES[$field]['name'])) {
            return [];
        }
        $raw = $_FILES[$field];
        if (!is_array($raw['name'])) {
            return [[
                'name' => (string) $raw['name'],
                'type' => (string) ($raw['type'] ?? ''),
                'tmp_name' => (string) ($raw['tmp_name'] ?? ''),
                'error' => (int) ($raw['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($raw['size'] ?? 0),
            ]];
        }
        $files = [];
        foreach ($raw['name'] as $i => $name) {
            $files[] = [
                'name' => (string) $name,
                'type' => (string) ($raw['type'][$i] ?? ''),
                'tmp_name' => (string) ($raw['tmp_name'][$i] ?? ''),
                'error' => (int) ($raw['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($raw['size'][$i] ?? 0),
            ];
        }
        return $files;
    }

    private static function extension(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    private static function detectMime(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return (string) $finfo->file($path);
    }

    private static function safeOriginalName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: 'dosya';
        return trim($name, ' ._') ?: 'dosya';
    }
}
