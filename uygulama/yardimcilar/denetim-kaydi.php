<?php

declare(strict_types=1);

class AuditLog
{
    private const ENUM_ACTIONS = [
        'create',
        'update',
        'delete',
        'verify',
        'unverify',
        'login',
        'login_fail',
        'export',
        'access_denied',
        'role_change',
        'password_reset',
        'status_change',
        'soft_delete',
        'restore',
        'notify',
        'session_kill',
    ];

    public static function log(
        PDO    $pdo,
        string $action,
        string $entityType,
        int    $entityId,
        $oldValue = null,
        $newValue = null
    ): ?int {
        try {
            $loggedAction = in_array($action, self::ENUM_ACTIONS, true) ? $action : 'update';
            $meta = self::buildContextMeta($action);

            $contextMeta = [
                '_audit_action'   => $action,
                '_request_method' => $meta['request_method'],
                '_request_uri'    => $meta['request_uri'],
                '_actor_role'     => $meta['actor_role'],
            ];

            $stmt = $pdo->prepare(
                "INSERT INTO audit_log
                    (user_id, action, entity_type, entity_id, old_value, new_value,
                     ip_address, user_agent, session_id, referer,
                     request_method, request_uri, actor_role, actor_full_name)
                 VALUES
                    (:uid, :action, :entity_type, :entity_id, :old_value, :new_value,
                     :ip, :ua, :sid, :ref,
                     :method, :uri, :role, :fullname)"
            );
            $stmt->execute([
                ':uid'         => Auth::getUserId(),
                ':action'      => $loggedAction,
                ':entity_type' => $entityType,
                ':entity_id'   => $entityId,
                ':old_value'   => $oldValue !== null
                    ? json_encode(self::normalizePayload($oldValue, $contextMeta), JSON_UNESCAPED_UNICODE)
                    : null,
                ':new_value'   => json_encode(self::normalizePayload($newValue, $contextMeta), JSON_UNESCAPED_UNICODE),
                ':ip'          => $meta['ip_address'],
                ':ua'          => $meta['user_agent'],
                ':sid'         => $meta['session_id'],
                ':ref'         => $meta['referer'],
                ':method'      => $meta['request_method'],
                ':uri'         => $meta['request_uri'],
                ':role'        => $meta['actor_role'],
                ':fullname'    => $meta['actor_full_name'],
            ]);

            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('[SECAP][AUDIT] Log yazma hatasi: ' . $e->getMessage());
            return null;
        }
    }

    public static function logAccessDenied(PDO $pdo, string $entityType, int $entityId = 0, array $context = []): void
    {
        self::log($pdo, 'access_denied', $entityType, $entityId, null, $context);
    }

    public static function logExport(PDO $pdo, string $entityType, array $context = []): void
    {
        self::log($pdo, 'export', $entityType, 0, null, $context);
    }

    public static function softDelete(
        PDO    $pdo,
        string $entityType,
        int    $entityId,
        array  $oldValue,
        string $reason
    ): ?int {
        $newValue = [
            'deleted_at'    => date('Y-m-d H:i:s'),
            'deleted_by'    => Auth::getUserId(),
            'delete_reason' => $reason,
        ];
        return self::log($pdo, 'soft_delete', $entityType, $entityId, $oldValue, $newValue);
    }

    public static function restore(
        PDO    $pdo,
        string $entityType,
        int    $entityId,
        array  $newValue
    ): ?int {
        $oldValue = [
            'deleted_at'    => null,
            'deleted_by'    => null,
            'delete_reason' => null,
            '_note'         => 'Record was soft-deleted before restore',
        ];
        return self::log($pdo, 'restore', $entityType, $entityId, $oldValue, $newValue);
    }

    public static function notify(
        PDO     $pdo,
        string  $type,
        string  $title,
        string  $message,
        ?string $link = null,
        ?int    $auditLogId = null,
        ?int    $recipientId = null
    ): void {
        $relatedType = $auditLogId !== null ? 'audit_log' : null;
        $relatedId = $auditLogId !== null ? $auditLogId : null;

        if ($recipientId !== null) {
            NotificationService::create(
                $pdo,
                $recipientId,
                $type,
                $title,
                $message,
                $link,
                'normal',
                $relatedType,
                $relatedId,
                null,
                false
            );
            return;
        }

        NotificationService::notifySuperAdmins(
            $pdo,
            $type,
            $title,
            $message,
            $link,
            'normal',
            $relatedType,
            $relatedId,
            null,
            false
        );
    }

    

    public static function sanitizeNotificationLink(?string $link): ?string
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
        if (preg_match('#^https?://#i', $trimmed) === 1) {
            return $trimmed;
        }
        return null;
    }

    public static function getUnreadCount(PDO $pdo, int $userId): int
    {
        return NotificationService::unreadCount($pdo, $userId);
    }

    private static function buildContextMeta(string $action): array
    {
        $fullName = null;
        $role     = Auth::getRole();
        if (Auth::isLoggedIn() && isset($_SESSION['user']['full_name'])) {
            $fullName = (string) $_SESSION['user']['full_name'];
        }

        $sessionId = null;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $hash = Auth::sessionIdHash();
            if ($hash !== '') {
                $sessionId = $hash;
            }
        }

        return [
            'ip_address'      => ClientIp::get(),
            'user_agent'      => isset($_SERVER['HTTP_USER_AGENT'])
                ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255)
                : null,
            'session_id'      => $sessionId,
            'referer'         => isset($_SERVER['HTTP_REFERER'])
                ? mb_substr((string) $_SERVER['HTTP_REFERER'], 0, 500)
                : null,
            'request_method'  => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'request_uri'     => isset($_SERVER['REQUEST_URI'])
                ? mb_substr((string) $_SERVER['REQUEST_URI'], 0, 500)
                : 'CLI',
            'actor_role'      => $role,
            'actor_full_name' => $fullName,
        ];
    }

    private static function normalizePayload($payload, array $contextMeta): array
    {
        if (is_array($payload)) {
            return $payload + $contextMeta;
        }

        if ($payload === null) {
            return $contextMeta;
        }

        return ['value' => $payload] + $contextMeta;
    }
}
