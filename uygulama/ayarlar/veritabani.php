<?php

declare(strict_types=1);

class Database
{
    private static ?Database $instance = null;
    private static array $config = [];
    private PDO $pdo;

    public static function configure(array $env): void
    {
        self::$config = $env;
    }

    

    private static function applySessionVariables(PDO $pdo, array $config): void
    {
        $charset = (string) ($config['DB_CHARSET'] ?? 'utf8mb4');
        if (!preg_match('/^[a-z0-9_]+$/i', $charset)) {
            $charset = 'utf8mb4';
        }

        $allowedCollations = [
            'utf8mb4_unicode_ci',
            'utf8mb4_turkish_ci',
            'utf8mb4_general_ci',
        ];
        $requested = trim((string) ($config['DB_CONNECTION_COLLATION'] ?? ''));
        if ($requested !== '' && in_array($requested, $allowedCollations, true)) {
            try {
                $pdo->exec("SET NAMES {$charset} COLLATE {$requested}");
            } catch (PDOException $e) {
                error_log('[SECAP][DB] SET NAMES+COLLATE atlandı: ' . $e->getMessage());
                try {
                    $pdo->exec("SET NAMES {$charset} COLLATE utf8mb4_unicode_ci");
                } catch (PDOException $e2) {
                    error_log('[SECAP][DB] SET NAMES unicode atlandı: ' . $e2->getMessage());
                    $pdo->exec("SET NAMES {$charset}");
                }
            }
        } else {
            try {
                $pdo->exec("SET NAMES {$charset} COLLATE utf8mb4_turkish_ci");
            } catch (PDOException $e) {
                error_log('[SECAP][DB] turkish_ci yok, unicode deneniyor: ' . $e->getMessage());
                try {
                    $pdo->exec("SET NAMES {$charset} COLLATE utf8mb4_unicode_ci");
                } catch (PDOException $e2) {
                    error_log('[SECAP][DB] unicode atlandı: ' . $e2->getMessage());
                    $pdo->exec("SET NAMES {$charset}");
                }
            }
        }

        try {
            $pdo->exec("SET time_zone = '+03:00'");
        } catch (PDOException $e) {
            error_log('[SECAP][DB] time_zone atlandı: ' . $e->getMessage());
        }

        try {
            $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        } catch (PDOException $e) {
            error_log('[SECAP][DB] sql_mode atlandı: ' . $e->getMessage());
        }
    }

    private function __construct()
    {
        $host    = self::$config['DB_HOST']    ?? 'localhost';
        $port    = self::$config['DB_PORT']    ?? '3306';
        $dbname  = self::$config['DB_NAME']    ?? 'secap_portal';
        $user    = self::$config['DB_USER']    ?? 'secap_user';
        $pass    = self::$config['DB_PASS']    ?? '';
        $charset = self::$config['DB_CHARSET'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
            self::applySessionVariables($this->pdo, self::$config);
        } catch (PDOException $e) {
            if ($host === 'localhost') {
                try {
                    $fallbackDsn = "mysql:host=127.0.0.1;port={$port};dbname={$dbname};charset={$charset}";
                    $this->pdo = new PDO($fallbackDsn, $user, $pass, $options);
                    self::applySessionVariables($this->pdo, self::$config);
                    return;
                } catch (PDOException $fallback) {
                    error_log('[SECAP][DB] Bağlantı hatası: ' . $fallback->getMessage());
                }
            } else {
                error_log('[SECAP][DB] Bağlantı hatası: ' . $e->getMessage());
            }
            throw new RuntimeException('Veritabanı bağlantısı kurulamadı. Lütfen daha sonra tekrar deneyin.');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    private function __clone() {}

    public function __wakeup(): void
    {
        throw new \Exception('Singleton unserialize edilemez.');
    }
}
