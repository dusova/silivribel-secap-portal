<?php

declare(strict_types=1);

final class NotificationService
{
    private const PRIORITIES = ['low', 'normal', 'high', 'critical'];

    public static function create(
        PDO $pdo,
        int $recipientUserId,
        string $eventKey,
        string $title,
        string $message,
        ?string $link = null,
        string $priority = 'normal',
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?string $dedupeKey = null,
        bool $email = false
    ): ?int {
        if ($recipientUserId <= 0) {
            return null;
        }

        $priority = in_array($priority, self::PRIORITIES, true) ? $priority : 'normal';
        $eventKey = mb_substr(trim($eventKey) ?: 'generic', 0, 100);
        $title = mb_substr(trim($title) ?: 'Bildirim', 0, 200);
        $message = trim($message);
        $dedupeKey = $dedupeKey !== null ? mb_substr(trim($dedupeKey), 0, 190) : null;
        if ($dedupeKey === '') {
            $dedupeKey = null;
        }

        try {
            if ($dedupeKey !== null) {
                $existing = $pdo->prepare(
                    'SELECT id FROM notifications
                     WHERE recipient_user_id = :uid AND dedupe_key = :dedupe
                     LIMIT 1'
                );
                $existing->execute([':uid' => $recipientUserId, ':dedupe' => $dedupeKey]);
                $existingId = (int) $existing->fetchColumn();
                if ($existingId > 0) {
                    return $existingId;
                }
            }

            $stmt = $pdo->prepare(
                "INSERT INTO notifications
                    (recipient_user_id, event_key, title, message, link, priority,
                     related_type, related_id, dedupe_key, email_status)
                 VALUES
                    (:uid, :event_key, :title, :message, :link, :priority,
                     :related_type, :related_id, :dedupe_key, :email_status)"
            );
            $stmt->execute([
                ':uid' => $recipientUserId,
                ':event_key' => $eventKey,
                ':title' => $title,
                ':message' => $message,
                ':link' => self::sanitizeLink($link),
                ':priority' => $priority,
                ':related_type' => $relatedType !== null ? mb_substr($relatedType, 0, 80) : null,
                ':related_id' => $relatedId,
                ':dedupe_key' => $dedupeKey,
                ':email_status' => $email ? 'skipped' : 'not_required',
            ]);
            $notificationId = (int) $pdo->lastInsertId();

            if ($email) {
                self::queueEmailForNotification($pdo, $notificationId, $recipientUserId, $title, $message);
            }

            return $notificationId;
        } catch (PDOException $e) {
            error_log('[SECAP][NOTIFY] Bildirim yazma hatasi: ' . $e->getMessage());
            return null;
        }
    }

    public static function notifyUser(
        PDO $pdo,
        int $userId,
        string $eventKey,
        string $title,
        string $message,
        ?string $link = null,
        string $priority = 'normal',
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?string $dedupeKey = null,
        bool $emailImportant = true
    ): void {
        self::create(
            $pdo,
            $userId,
            $eventKey,
            $title,
            $message,
            $link,
            $priority,
            $relatedType,
            $relatedId,
            $dedupeKey,
            $emailImportant
        );
    }

    public static function notifyAdmins(
        PDO $pdo,
        string $eventKey,
        string $title,
        string $message,
        ?string $link = null,
        string $priority = 'normal',
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?string $dedupeKeyPrefix = null,
        bool $emailImportant = true
    ): void {
        $stmt = $pdo->query(
            "SELECT id, role
             FROM users
             WHERE role IN ('admin','super_admin')
               AND is_active = 1
               AND deleted_at IS NULL
             ORDER BY role, full_name"
        );

        foreach ($stmt->fetchAll() as $user) {
            $role = (string) $user['role'];
            $userId = (int) $user['id'];
            $dedupeKey = $dedupeKeyPrefix !== null ? $dedupeKeyPrefix . ':u' . $userId : null;
            self::create(
                $pdo,
                $userId,
                $eventKey,
                $title,
                $message,
                $link,
                $priority,
                $relatedType,
                $relatedId,
                $dedupeKey,
                $emailImportant && $role === 'admin'
            );
        }
    }

    public static function notifySuperAdmins(
        PDO $pdo,
        string $eventKey,
        string $title,
        string $message,
        ?string $link = null,
        string $priority = 'normal',
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?string $dedupeKeyPrefix = null,
        bool $emailImportant = false
    ): void {
        $stmt = $pdo->query(
            "SELECT id
             FROM users
             WHERE role = 'super_admin'
               AND is_active = 1
               AND deleted_at IS NULL
             ORDER BY full_name"
        );

        foreach ($stmt->fetchAll() as $user) {
            $userId = (int) $user['id'];
            $dedupeKey = $dedupeKeyPrefix !== null ? $dedupeKeyPrefix . ':u' . $userId : null;
            self::create(
                $pdo,
                $userId,
                $eventKey,
                $title,
                $message,
                $link,
                $priority,
                $relatedType,
                $relatedId,
                $dedupeKey,
                $emailImportant
            );
        }
    }

    public static function notifyDepartment(
        PDO $pdo,
        int $departmentId,
        string $eventKey,
        string $title,
        string $message,
        ?string $link = null,
        string $priority = 'normal',
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?string $dedupeKeyPrefix = null,
        bool $emailImportant = true
    ): void {
        if ($departmentId <= 0) {
            return;
        }

        $stmt = $pdo->prepare(
            "SELECT id
             FROM users
             WHERE department_id = :dept
               AND role = 'department_user'
               AND is_active = 1
               AND deleted_at IS NULL
             ORDER BY full_name"
        );
        $stmt->execute([':dept' => $departmentId]);

        foreach ($stmt->fetchAll() as $user) {
            $userId = (int) $user['id'];
            $dedupeKey = $dedupeKeyPrefix !== null ? $dedupeKeyPrefix . ':u' . $userId : null;
            self::create(
                $pdo,
                $userId,
                $eventKey,
                $title,
                $message,
                $link,
                $priority,
                $relatedType,
                $relatedId,
                $dedupeKey,
                $emailImportant
            );
        }
    }

    public static function notifyActionDepartments(
        PDO $pdo,
        int $actionId,
        string $eventKey,
        string $title,
        string $message,
        ?string $link = null,
        string $priority = 'normal',
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?string $dedupeKeyPrefix = null
    ): void {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT department_id
             FROM action_departments
             WHERE action_id = :action_id"
        );
        $stmt->execute([':action_id' => $actionId]);
        foreach ($stmt->fetchAll() as $row) {
            self::notifyDepartment(
                $pdo,
                (int) $row['department_id'],
                $eventKey,
                $title,
                $message,
                $link,
                $priority,
                $relatedType,
                $relatedId,
                $dedupeKeyPrefix
            );
        }
    }

    public static function runDailyChecksForSession(PDO $pdo, int $userId): void
    {
        if ($userId <= 0 || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $today = date('Y-m-d');
        $key = 'notification_checks_ran_on';
        if (($_SESSION[$key] ?? null) === $today) {
            return;
        }

        try {
            self::ensureMissingDataNotifications($pdo, $userId);
            $_SESSION[$key] = $today;
        } catch (Throwable $e) {
            error_log('[SECAP][NOTIFY_CHECK] ' . $e->getMessage());
        }
    }

    public static function ensureMissingDataNotifications(PDO $pdo, int $userId): int
    {
        $userStmt = $pdo->prepare(
            "SELECT id, role, department_id
             FROM users
             WHERE id = :id AND is_active = 1 AND deleted_at IS NULL
             LIMIT 1"
        );
        $userStmt->execute([':id' => $userId]);
        $user = $userStmt->fetch();
        if (!$user) {
            return 0;
        }

        $year = (int) date('Y');
        $week = date('o-W');
        $created = 0;
        $role = (string) $user['role'];

        if ($role === 'department_user') {
            $stmt = $pdo->prepare(
                "SELECT DISTINCT k.id, k.name, k.unit, a.code, a.title
                 FROM kpis k
                 JOIN actions a ON a.id = k.action_id
                 LEFT JOIN action_departments ad
                        ON ad.action_id = a.id
                       AND ad.department_id = :dept2
                 WHERE k.is_active = 1
                   AND k.deleted_at IS NULL
                   AND a.deleted_at IS NULL
                   AND a.status != 'cancelled'
                   AND (a.responsible_department_id = :dept OR ad.department_id IS NOT NULL)
                   AND NOT EXISTS (
                       SELECT 1
                       FROM data_entries de
                       WHERE de.kpi_id = k.id
                         AND de.department_id = :dept3
                         AND de.year = :year
                         AND de.deleted_at IS NULL
                         AND de.workflow_status IN ('submitted','needs_revision','approved')
                   )
                 ORDER BY a.code, k.name
                 LIMIT 20"
            );
            $deptId = (int) $user['department_id'];
            $stmt->execute([
                ':dept' => $deptId,
                ':dept2' => $deptId,
                ':dept3' => $deptId,
                ':year' => $year,
            ]);
            foreach ($stmt->fetchAll() as $row) {
                $before = $created;
                $id = self::create(
                    $pdo,
                    $userId,
                    'missing_data',
                    'Eksik veri girişi',
                    "{$row['code']} eylemindeki \"{$row['name']}\" verisi için {$year} yılı girişi bekleniyor.",
                    BASE_PATH . '/mudurluk/veri-girisi?kpi_id=' . (int) $row['id'] . '&year=' . $year,
                    'high',
                    'kpis',
                    (int) $row['id'],
                    'missing_data:' . $userId . ':' . $year . ':' . (int) $row['id'] . ':' . $week,
                    true
                );
                if ($id !== null) {
                    $created = $before + 1;
                }
            }
            return $created;
        }

        if (in_array($role, ['admin', 'super_admin'], true)) {
            $pending = (int) $pdo->query(
                "SELECT COUNT(*)
                 FROM data_entries
                 WHERE workflow_status IN ('submitted','needs_revision')
                   AND deleted_at IS NULL"
            )->fetchColumn();
            if ($pending > 0) {
                $id = self::create(
                    $pdo,
                    $userId,
                    'pending_review',
                    'Onay bekleyen veri özeti',
                    "{$pending} veri girişi değerlendirme bekliyor.",
                    BASE_PATH . '/yonetim/veri-onay?workflow_status=submitted',
                    'high',
                    'data_entries',
                    null,
                    'pending_review:' . $userId . ':' . $year . ':' . $week,
                    $role === 'admin'
                );
                return $id !== null ? 1 : 0;
            }
        }

        return 0;
    }

    public static function unreadCount(PDO $pdo, int $userId): int
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM notifications
                 WHERE recipient_user_id = :uid AND is_read = 0'
            );
            $stmt->execute([':uid' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    public static function latest(PDO $pdo, int $userId, int $limit = 10): array
    {
        $limit = max(1, min(30, $limit));
        $stmt = $pdo->prepare(
            "SELECT id, event_key, title, message, link, priority, is_read, created_at
             FROM notifications
             WHERE recipient_user_id = :uid
             ORDER BY created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([self::class, 'decorate'], $stmt->fetchAll());
    }

    public static function markRead(PDO $pdo, int $userId, int $notificationId): bool
    {
        $stmt = $pdo->prepare(
            'UPDATE notifications
             SET is_read = 1, read_at = NOW()
             WHERE id = :id
               AND recipient_user_id = :uid
               AND is_read = 0'
        );
        $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function markAllRead(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare(
            'UPDATE notifications
             SET is_read = 1, read_at = NOW()
             WHERE recipient_user_id = :uid
               AND is_read = 0'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->rowCount();
    }

    public static function eventLabels(): array
    {
        return [
            'action_assigned' => ['label' => 'Eylem Atandı', 'cls' => 'success', 'icon' => 'bi-lightning-charge'],
            'activity_assigned' => ['label' => 'Faaliyet Atandı', 'cls' => 'success', 'icon' => 'bi-layers'],
            'kpi_assigned' => ['label' => 'KPI Atandı', 'cls' => 'primary', 'icon' => 'bi-bar-chart'],
            'data_submitted' => ['label' => 'Onaya Gönderildi', 'cls' => 'warning', 'icon' => 'bi-send-check'],
            'data_resubmitted' => ['label' => 'Tekrar Gönderildi', 'cls' => 'warning', 'icon' => 'bi-arrow-repeat'],
            'entry_approved' => ['label' => 'Veri Onaylandı', 'cls' => 'success', 'icon' => 'bi-check-circle'],
            'entry_rejected' => ['label' => 'Veri Reddedildi', 'cls' => 'danger', 'icon' => 'bi-x-circle'],
            'entry_revision' => ['label' => 'Düzeltme İstendi', 'cls' => 'info', 'icon' => 'bi-arrow-repeat'],
            'missing_data' => ['label' => 'Eksik Veri', 'cls' => 'warning', 'icon' => 'bi-calendar-exclamation'],
            'pending_review' => ['label' => 'Onay Bekliyor', 'cls' => 'warning', 'icon' => 'bi-hourglass-split'],
            'backup_failed' => ['label' => 'Yedek Uyarısı', 'cls' => 'danger', 'icon' => 'bi-database-exclamation'],
            'security' => ['label' => 'Güvenlik', 'cls' => 'danger', 'icon' => 'bi-shield-exclamation'],
            'soft_delete' => ['label' => 'Silme', 'cls' => 'danger', 'icon' => 'bi-trash'],
            'restore' => ['label' => 'Geri Alma', 'cls' => 'success', 'icon' => 'bi-arrow-counterclockwise'],
            'role_change' => ['label' => 'Rol Değişimi', 'cls' => 'warning', 'icon' => 'bi-person-badge'],
            'password_reset' => ['label' => 'Şifre Sıfırlama', 'cls' => 'warning', 'icon' => 'bi-key'],
            'user_created' => ['label' => 'Yeni Kullanıcı', 'cls' => 'info', 'icon' => 'bi-person-plus'],
            'status_change' => ['label' => 'Durum Değişimi', 'cls' => 'info', 'icon' => 'bi-toggle-on'],
            'generic' => ['label' => 'Bildirim', 'cls' => 'primary', 'icon' => 'bi-bell'],
        ];
    }

    public static function decorate(array $notification): array
    {
        $labels = self::eventLabels();
        $eventKey = (string) ($notification['event_key'] ?? 'generic');
        $cfg = $labels[$eventKey] ?? ['label' => $eventKey, 'cls' => self::priorityClass((string) ($notification['priority'] ?? 'normal')), 'icon' => 'bi-bell'];

        return [
            'id' => (int) $notification['id'],
            'event_key' => $eventKey,
            'type' => $eventKey,
            'type_label' => $cfg['label'],
            'type_cls' => $cfg['cls'],
            'icon' => $cfg['icon'],
            'title' => (string) $notification['title'],
            'message' => (string) $notification['message'],
            'link' => $notification['link'] ?? null,
            'priority' => (string) ($notification['priority'] ?? 'normal'),
            'is_read' => (int) ($notification['is_read'] ?? 0),
            'created_at' => (string) $notification['created_at'],
        ];
    }

    public static function sanitizeLink(?string $link): ?string
    {
        if ($link === null) {
            return null;
        }
        $trimmed = trim($link);
        if ($trimmed === '') {
            return null;
        }
        if (strlen($trimmed) > 500) {
            $trimmed = substr($trimmed, 0, 500);
        }
        if (str_starts_with($trimmed, '/') && !str_starts_with($trimmed, '//')) {
            return $trimmed;
        }
        return null;
    }

    private static function queueEmailForNotification(
        PDO $pdo,
        int $notificationId,
        int $userId,
        string $subject,
        string $body
    ): void {
        if (!defined('SMTP_ENABLED') || !SMTP_ENABLED) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT email, full_name
             FROM users
             WHERE id = :id AND is_active = 1 AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        if (!$user || !filter_var((string) $user['email'], FILTER_VALIDATE_EMAIL)) {
            self::updateEmailStatus($pdo, $notificationId, 'failed', null);
            return;
        }

        $queue = $pdo->prepare(
            "INSERT INTO email_queue (recipient_email, recipient_name, subject, body, status, last_error)
             VALUES (:email, :name, :subject, :body, 'pending', NULL)"
        );
        $queue->execute([
            ':email' => (string) $user['email'],
            ':name' => mb_substr((string) $user['full_name'], 0, 150),
            ':subject' => mb_substr($subject, 0, 220),
            ':body' => $body,
        ]);
        $emailId = (int) $pdo->lastInsertId();
        self::updateEmailStatus($pdo, $notificationId, 'queued', $emailId);

        if ($emailId > 0 && class_exists('V2')) {
            V2::sendQueuedEmail($pdo, $emailId);
            $statusStmt = $pdo->prepare('SELECT status FROM email_queue WHERE id = :id LIMIT 1');
            $statusStmt->execute([':id' => $emailId]);
            $queueStatus = (string) $statusStmt->fetchColumn();
            if (in_array($queueStatus, ['sent', 'failed'], true)) {
                self::updateEmailStatus($pdo, $notificationId, $queueStatus, $emailId);
            }
        }
    }

    private static function updateEmailStatus(PDO $pdo, int $notificationId, string $status, ?int $emailId): void
    {
        $stmt = $pdo->prepare(
            'UPDATE notifications
             SET email_status = :status, email_queue_id = :email_id
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $status,
            ':email_id' => $emailId,
            ':id' => $notificationId,
        ]);
    }

    private static function priorityClass(string $priority): string
    {
        return [
            'low' => 'secondary',
            'normal' => 'primary',
            'high' => 'warning',
            'critical' => 'danger',
        ][$priority] ?? 'primary';
    }
}
