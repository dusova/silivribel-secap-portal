<?php

declare(strict_types=1);

class AuditLog
{
    private const LEGACY_ACTIONS = [
        'create',
        'update',
        'delete',
        'verify',
        'unverify',
        'login',
        'login_fail',
    ];

    public static function log(
        PDO    $pdo,
        string $action,
        string $entityType,
        int    $entityId,
        mixed  $oldValue = null,
        mixed  $newValue = null
    ): void {
        try {
            $loggedAction = in_array($action, self::LEGACY_ACTIONS, true) ? $action : 'update';
            $contextMeta = [
                '_audit_action'   => $action,
                '_request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                '_request_uri'    => $_SERVER['REQUEST_URI'] ?? 'CLI',
                '_actor_role'     => Auth::getRole(),
            ];

            $stmt = $pdo->prepare(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_value, new_value, ip_address)
                 VALUES (:uid, :action, :entity_type, :entity_id, :old_value, :new_value, :ip)"
            );
            $stmt->execute([
                ':uid'         => Auth::getUserId(),
                ':action'      => $loggedAction,
                ':entity_type' => $entityType,
                ':entity_id'   => $entityId,
                ':old_value'   => $oldValue !== null ? json_encode(self::normalizePayload($oldValue, $contextMeta), JSON_UNESCAPED_UNICODE) : null,
                ':new_value'   => json_encode(self::normalizePayload($newValue, $contextMeta), JSON_UNESCAPED_UNICODE),
                ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (PDOException $e) {
            error_log('[SECAP][AUDIT] Log yazma hatası: ' . $e->getMessage());
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

    private static function normalizePayload(mixed $payload, array $contextMeta): array
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
