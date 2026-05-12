<?php

declare(strict_types=1);

class SoftDelete
{
    public const TABLES = [
        'actions' => [
            'entity_type'  => 'actions',
            'label'        => 'Eylem',
            'label_plural' => 'Eylemler',
            'title_field'  => 'title',
            'link'         => '/yonetim/eylem-formu?id=',
        ],
        'activities' => [
            'entity_type'  => 'activities',
            'label'        => 'Faaliyet',
            'label_plural' => 'Faaliyetler',
            'title_field'  => 'title',
            'link'         => '/yonetim/faaliyet-formu?id=',
        ],
        'kpis' => [
            'entity_type'  => 'kpis',
            'label'        => 'KPI',
            'label_plural' => "KPI'lar",
            'title_field'  => 'name',
            'link'         => '/yonetim/kpi-formu?id=',
        ],
        'data_entries' => [
            'entity_type'  => 'data_entries',
            'label'        => 'Veri Girişi',
            'label_plural' => 'Veri Girişleri',
            'title_field'  => null,
            'link'         => '/yonetim/veri-onay',
        ],
        'users' => [
            'entity_type'  => 'users',
            'label'        => 'Kullanıcı',
            'label_plural' => 'Kullanıcılar',
            'title_field'  => 'full_name',
            'link'         => '/yonetim/kullanici-formu?id=',
        ],
    ];

    public static function delete(
        PDO    $pdo,
        string $table,
        int    $id,
        string $reason,
        array  $snapshot
    ): bool {
        self::assertAllowed($table);
        self::assertReason($reason);

        if (!Auth::isSuperAdmin()) {
            return false;
        }

        $stmt = $pdo->prepare(
            "UPDATE `{$table}`
             SET deleted_at = NOW(), deleted_by = :uid, delete_reason = :r
             WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([
            ':uid' => Auth::getUserId(),
            ':r'   => $reason,
            ':id'  => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            return false;
        }

        $cfg        = self::TABLES[$table];
        $entityType = $cfg['entity_type'];
        $auditId    = AuditLog::softDelete($pdo, $entityType, $id, $snapshot + ['_delete_reason' => $reason], $reason);

        $title   = self::buildTitle($snapshot, $cfg['title_field']);
        $label   = $cfg['label'];
        $actor   = Auth::getFullName();
        $message = "{$actor}, {$label} kaydini ('{$title}') cop kutusuna tasidi. Sebep: {$reason}";
        $link    = defined('BASE_PATH') ? BASE_PATH . '/yonetim/cop-kutusu' : '/yonetim/cop-kutusu';

        AuditLog::notify($pdo, 'soft_delete', "{$label} silindi", $message, $link, $auditId);

        return true;
    }

    public static function restore(
        PDO    $pdo,
        string $table,
        int    $id
    ): bool {
        self::assertAllowed($table);

        if (!Auth::isSuperAdmin()) {
            return false;
        }

        $snap = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = :id AND deleted_at IS NOT NULL LIMIT 1");
        $snap->execute([':id' => $id]);
        $row = $snap->fetch();
        if (!$row) {
            return false;
        }

        $stmt = $pdo->prepare(
            "UPDATE `{$table}`
             SET deleted_at = NULL, deleted_by = NULL, delete_reason = NULL
             WHERE id = :id AND deleted_at IS NOT NULL"
        );
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            return false;
        }

        $cfg        = self::TABLES[$table];
        $entityType = $cfg['entity_type'];
        $auditId    = AuditLog::restore($pdo, $entityType, $id, [
            'restored_at' => date('Y-m-d H:i:s'),
            'restored_by' => Auth::getUserId(),
            'snapshot'    => $row,
        ]);

        $title   = self::buildTitle($row, $cfg['title_field']);
        $label   = $cfg['label'];
        $actor   = Auth::getFullName();
        $message = "{$actor}, {$label} kaydini ('{$title}') geri aldi.";
        $link    = defined('BASE_PATH') ? BASE_PATH . '/yonetim/cop-kutusu' : '/yonetim/cop-kutusu';

        AuditLog::notify($pdo, 'restore', "{$label} geri alindi", $message, $link, $auditId);

        return true;
    }

    public static function fetchTrashed(PDO $pdo, string $table, int $limit = 200): array
    {
        self::assertAllowed($table);

        $cfg = self::TABLES[$table];

        if ($table === 'users') {
            $sql = "SELECT u.*, d.name AS dept_name,
                           dl.full_name AS deleter_name
                    FROM   `{$table}` u
                    LEFT JOIN departments d  ON d.id = u.department_id
                    LEFT JOIN users dl       ON dl.id = u.deleted_by
                    WHERE  u.deleted_at IS NOT NULL
                    ORDER  BY u.deleted_at DESC
                    LIMIT  :lim";
        } elseif ($table === 'data_entries') {
            $sql = "SELECT de.*, k.name AS kpi_name, a.code AS action_code,
                           d.name AS dept_name, dl.full_name AS deleter_name
                    FROM   `{$table}` de
                    LEFT JOIN kpis       k  ON k.id = de.kpi_id
                    LEFT JOIN actions    a  ON a.id = de.action_id
                    LEFT JOIN departments d  ON d.id = de.department_id
                    LEFT JOIN users      dl ON dl.id = de.deleted_by
                    WHERE  de.deleted_at IS NOT NULL
                    ORDER  BY de.deleted_at DESC
                    LIMIT  :lim";
        } elseif ($table === 'kpis') {
            $sql = "SELECT k.*, a.code AS action_code, a.title AS action_title,
                           dl.full_name AS deleter_name
                    FROM   `{$table}` k
                    LEFT JOIN actions a  ON a.id = k.action_id
                    LEFT JOIN users dl   ON dl.id = k.deleted_by
                    WHERE  k.deleted_at IS NOT NULL
                    ORDER  BY k.deleted_at DESC
                    LIMIT  :lim";
        } elseif ($table === 'activities') {
            $sql = "SELECT act.*, a.code AS action_code, d.name AS dept_name,
                           dl.full_name AS deleter_name
                    FROM   `{$table}` act
                    LEFT JOIN actions     a  ON a.id = act.action_id
                    LEFT JOIN departments d  ON d.id = act.department_id
                    LEFT JOIN users       dl ON dl.id = act.deleted_by
                    WHERE  act.deleted_at IS NOT NULL
                    ORDER  BY act.deleted_at DESC
                    LIMIT  :lim";
        } else {
            $sql = "SELECT a.*, d.name AS dept_name, dl.full_name AS deleter_name
                    FROM   `{$table}` a
                    LEFT JOIN departments d  ON d.id = a.responsible_department_id
                    LEFT JOIN users      dl ON dl.id = a.deleted_by
                    WHERE  a.deleted_at IS NOT NULL
                    ORDER  BY a.deleted_at DESC
                    LIMIT  :lim";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countTrashed(PDO $pdo): array
    {
        $counts = [];
        foreach (array_keys(self::TABLES) as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE deleted_at IS NOT NULL");
                $counts[$table] = (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                $counts[$table] = 0;
            }
        }
        return $counts;
    }

    private static function assertAllowed(string $table): void
    {
        if (!isset(self::TABLES[$table])) {
            throw new InvalidArgumentException("Soft-delete desteklenmeyen tablo: {$table}");
        }
    }

    private static function assertReason(string $reason): void
    {
        $len = mb_strlen(trim($reason));
        if ($len < 3) {
            throw new InvalidArgumentException('Silme sebebi en az 3 karakter olmalidir.');
        }
        if ($len > 500) {
            throw new InvalidArgumentException('Silme sebebi 500 karakterden uzun olamaz.');
        }
    }

    private static function buildTitle(array $snapshot, ?string $field): string
    {
        if ($field !== null && !empty($snapshot[$field])) {
            return (string) $snapshot[$field];
        }
        return '#' . ($snapshot['id'] ?? '?');
    }
}
