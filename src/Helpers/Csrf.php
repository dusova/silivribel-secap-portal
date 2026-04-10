<?php

declare(strict_types=1);

class Csrf
{
    public static function generate(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate(string $token): bool
    {
        return !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function check(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        if (!self::validate($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
        }
        unset($_SESSION['csrf_token']);
    }

    public static function field(): string
    {
        $t = htmlspecialchars(self::generate(), ENT_QUOTES, 'UTF-8');
        return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$t}\">";
    }
}
