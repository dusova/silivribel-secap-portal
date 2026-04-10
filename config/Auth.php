<?php

declare(strict_types=1);

class Auth
{
    private const SESSION_TIMEOUT = 7200;
    private const ABSOLUTE_SESSION_TIMEOUT = 28800;
    private const SESSION_SYNC_INTERVAL = 60;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_httponly', '1');
            session_name('SECAPSESSID');
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => self::getSessionCookiePath(),
                'domain'   => '',
                'secure'   => self::shouldUseSecureCookies(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }

        $_SESSION['issued_at'] ??= time();

        if ((time() - (int) $_SESSION['issued_at']) > self::ABSOLUTE_SESSION_TIMEOUT) {
            self::forceLogout('expired');
        }

        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > self::SESSION_TIMEOUT) {
                self::forceLogout('timeout');
            }
        }
        $_SESSION['last_activity'] = time();
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user']['id']);
    }

    public static function getRole(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function getDepartmentId(): ?int
    {
        return isset($_SESSION['user']['department_id'])
            ? (int) $_SESSION['user']['department_id']
            : null;
    }

    public static function getUserId(): ?int
    {
        return isset($_SESSION['user']['id'])
            ? (int) $_SESSION['user']['id']
            : null;
    }

    public static function isAdmin(): bool
    {
        return self::getRole() === 'admin';
    }

    public static function getFullName(): string
    {
        return $_SESSION['user']['full_name'] ?? '';
    }

    public static function requireLogin(): void
    {
        self::startSession();
        if (!self::isLoggedIn()) {
            self::redirectToLogin();
        }

        if (!self::syncSessionUser()) {
            self::forceLogout('refresh');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            $pdo = self::getPdo();
            if ($pdo instanceof PDO) {
                AuditLog::logAccessDenied($pdo, 'admin_area', 0, [
                    'path' => $_SERVER['PHP_SELF'] ?? '',
                ]);
            }

            self::renderForbidden();
        }
    }

    public static function canAccessAction(PDO $pdo, int $actionId): bool
    {
        if (!self::isLoggedIn()) {
            return false;
        }

        if (self::isAdmin()) {
            return true;
        }

        $deptId = self::getDepartmentId();

        $stmt = $pdo->prepare(
            'SELECT responsible_department_id FROM actions WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $actionId]);
        $action = $stmt->fetch();

        if (!$action) {
            return false;
        }

        if ((int) $action['responsible_department_id'] === $deptId) {
            return true;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM action_departments
             WHERE action_id = :action_id AND department_id = :dept_id'
        );
        $stmt->execute([':action_id' => $actionId, ':dept_id' => $deptId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function getAccessibleActionIds(PDO $pdo, bool $includeCancelled = false): array
    {
        if (!self::isLoggedIn()) {
            return [];
        }

        if (self::isAdmin()) {
            $sql = 'SELECT id FROM actions';
            if (!$includeCancelled) {
                $sql .= " WHERE status != 'cancelled'";
            }
            $sql .= ' ORDER BY id';

            return array_map('intval', array_column($pdo->query($sql)->fetchAll(), 'id'));
        }

        $deptId = self::getDepartmentId();
        $sql = "SELECT DISTINCT a.id
                FROM   actions a
                LEFT JOIN action_departments ad
                       ON ad.action_id = a.id
                      AND ad.department_id = :dept_id2
                WHERE  (a.responsible_department_id = :dept_id OR ad.department_id IS NOT NULL)";

        if (!$includeCancelled) {
            $sql .= " AND a.status != 'cancelled'";
        }

        $sql .= ' ORDER BY a.id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':dept_id'  => $deptId,
            ':dept_id2' => $deptId,
        ]);

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    public static function getSqlPlaceholders(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }

    public static function validateDataEntryPermission(PDO $pdo, int $kpiId): bool
    {
        if (!self::isLoggedIn()) {
            return false;
        }

        if (self::isAdmin()) {
            return true;
        }

        $stmt = $pdo->prepare(
            'SELECT a.id AS action_id, a.responsible_department_id
             FROM   kpis k
             JOIN   actions a ON a.id = k.action_id
             WHERE  k.id = :kpi_id
             LIMIT 1'
        );
        $stmt->execute([':kpi_id' => $kpiId]);
        $result = $stmt->fetch();

        if (!$result) {
            return false;
        }

        return self::canAccessAction($pdo, (int) $result['action_id']);
    }

    public static function getAccessibleDepartmentIds(PDO $pdo): array
    {
        if (self::isAdmin()) {
            return array_column(
                $pdo->query('SELECT id FROM departments WHERE is_active = 1')->fetchAll(),
                'id'
            );
        }

        $deptId = self::getDepartmentId();
        return [$deptId];
    }

    private const MAX_LOGIN_ATTEMPTS = 5;
    private const MAX_IP_LOGIN_ATTEMPTS = 20;
    private const LOGIN_LOCKOUT_MINUTES = 15;

    public static function isLoginAllowed(PDO $pdo, string $ip, string $username): bool
    {
        $userStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE username = :username
               AND ip_address = :ip
               AND success = 0
               AND attempted_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)"
        );
        $userStmt->execute([
            ':username' => $username,
            ':ip'       => $ip,
            ':minutes'  => self::LOGIN_LOCKOUT_MINUTES,
        ]);

        $ipStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = :ip
               AND success = 0
               AND attempted_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)"
        );
        $ipStmt->execute([
            ':ip'      => $ip,
            ':minutes' => self::LOGIN_LOCKOUT_MINUTES,
        ]);

        return (int) $userStmt->fetchColumn() < self::MAX_LOGIN_ATTEMPTS
            && (int) $ipStmt->fetchColumn() < self::MAX_IP_LOGIN_ATTEMPTS;
    }

    public static function logLoginAttempt(PDO $pdo, string $ip, string $username, bool $success): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO login_attempts (ip_address, username, success) VALUES (:ip, :user, :success)"
        );
        $stmt->execute([':ip' => $ip, ':user' => $username, ':success' => (int)$success]);
    }

    public static function getLockoutRemainingMinutes(PDO $pdo, string $ip, string $username): int
    {
        $stmt = $pdo->prepare(
            "SELECT attempted_at
             FROM login_attempts
             WHERE success = 0
               AND (ip_address = :ip OR username = :username)
             ORDER BY attempted_at DESC
             LIMIT 1"
        );
        $stmt->execute([':ip' => $ip, ':username' => $username]);
        $last = $stmt->fetchColumn();

        if (!$last) return 0;

        $unlockAt = strtotime($last) + (self::LOGIN_LOCKOUT_MINUTES * 60);
        $remaining = $unlockAt - time();

        return $remaining > 0 ? (int)ceil($remaining / 60) : 0;
    }

    public static function login(PDO $pdo, string $username, string $password): bool
    {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.department_id, u.password_hash, u.full_name, u.username, u.role,
                    d.name AS department_name
             FROM   users u
             JOIN   departments d ON d.id = u.department_id
             WHERE  u.username  = :username
               AND  u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'              => (int) $user['id'],
            'department_id'   => (int) $user['department_id'],
            'role'            => $user['role'],
            'full_name'       => $user['full_name'],
            'username'        => $user['username'],
            'department_name' => $user['department_name'],
            'pwd_sig'         => hash('sha256', $user['password_hash']),
        ];
        $_SESSION['issued_at'] = time();
        $_SESSION['last_sync_at'] = time();
        $_SESSION['last_activity'] = time();

        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute([':id' => $user['id']]);

        AuditLog::log($pdo, 'login', 'users', (int)$user['id']);

        return true;
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('SECAPSESSID');
            session_start();
        }
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function denyAccess(PDO $pdo, string $entityType, int $entityId = 0, array $context = []): never
    {
        AuditLog::logAccessDenied($pdo, $entityType, $entityId, $context);
        self::renderForbidden();
    }

    private static function syncSessionUser(): bool
    {
        if (!self::isLoggedIn()) {
            return false;
        }

        $lastSyncAt = (int) ($_SESSION['last_sync_at'] ?? 0);
        if ((time() - $lastSyncAt) < self::SESSION_SYNC_INTERVAL) {
            return true;
        }

        $pdo = self::getPdo();
        if (!$pdo instanceof PDO) {
            return true;
        }

        $stmt = $pdo->prepare(
            'SELECT u.id, u.department_id, u.password_hash, u.full_name, u.username, u.role,
                    d.name AS department_name
             FROM   users u
             JOIN   departments d ON d.id = u.department_id
             WHERE  u.id = :id
               AND  u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':id' => self::getUserId()]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        $currentSig = $_SESSION['user']['pwd_sig'] ?? null;
        $latestSig = hash('sha256', $user['password_hash']);
        if ($currentSig !== null && !hash_equals((string) $currentSig, $latestSig)) {
            return false;
        }

        $oldRole = $_SESSION['user']['role'] ?? null;
        $oldDept = (int) ($_SESSION['user']['department_id'] ?? 0);

        $_SESSION['user'] = [
            'id'              => (int) $user['id'],
            'department_id'   => (int) $user['department_id'],
            'role'            => $user['role'],
            'full_name'       => $user['full_name'],
            'username'        => $user['username'],
            'department_name' => $user['department_name'],
            'pwd_sig'         => $latestSig,
        ];
        $_SESSION['last_sync_at'] = time();

        if ($oldRole !== $user['role'] || $oldDept !== (int) $user['department_id']) {
            session_regenerate_id(true);
        }

        return true;
    }

    private static function getPdo(): ?PDO
    {
        if (!class_exists('Database')) {
            return null;
        }

        try {
            return Database::getInstance()->getConnection();
        } catch (Throwable) {
            return null;
        }
    }

    private static function renderForbidden(): never
    {
        http_response_code(403);
        include __DIR__ . '/../templates/shared/403.php';
        exit;
    }

    private static function redirectToLogin(?string $reason = null): never
    {
        $base = defined('BASE_PATH') ? BASE_PATH : '/secap';
        $url = $base . '/public/login.php';
        if ($reason !== null) {
            $url .= '?' . http_build_query([$reason => 1]);
        }
        header('Location: ' . $url);
        exit;
    }

    private static function forceLogout(string $reason): never
    {
        self::logout();
        self::redirectToLogin($reason);
    }

    private static function getSessionCookiePath(): string
    {
        $base = defined('BASE_PATH') ? trim((string) BASE_PATH) : '';
        return $base !== '' ? $base : '/';
    }

    private static function shouldUseSecureCookies(): bool
    {
        if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
            return true;
        }

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}
