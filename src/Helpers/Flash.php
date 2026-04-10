<?php

declare(strict_types=1);

class Flash
{
    public static function success(string $msg): void { self::set('success', $msg); }
    public static function error(string $msg): void   { self::set('error',   $msg); }
    public static function warning(string $msg): void { self::set('warning', $msg); }

    private static function set(string $type, string $message): void
    {
        $_SESSION['_flash'] = compact('type', 'message');
    }

    public static function render(): string
    {
        if (empty($_SESSION['_flash'])) {
            return '';
        }
        ['type' => $type, 'message' => $message] = $_SESSION['_flash'];
        unset($_SESSION['_flash']);

        $cls = match ($type) {
            'success' => 'alert-success',
            'error'   => 'alert-danger',
            'warning' => 'alert-warning',
            default   => 'alert-info',
        };
        $msg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <div class="alert {$cls} alert-dismissible fade show" role="alert">
            {$msg}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        HTML;
    }
}
