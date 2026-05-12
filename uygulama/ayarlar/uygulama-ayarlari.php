<?php

declare(strict_types=1);

final class AppConfig
{
    
    private static array $allowedHosts = [];

    
    private static array $trustProxyIps = [];

    private static string $sessionCookieDomain = '';

    public static function loadFromEnv(array $env): void
    {
        $ah = $env['ALLOWED_HOSTS'] ?? '';
        if (is_array($ah)) {
            self::$allowedHosts = array_values(array_filter(array_map('trim', $ah)));
        } else {
            self::$allowedHosts = array_values(array_filter(array_map('trim', explode(',', (string) $ah))));
        }
        self::$allowedHosts = array_values(array_unique(array_map(
            static fn (string $h): string => strtolower($h),
            self::$allowedHosts
        )));

        $tp = $env['TRUST_PROXY_IPS'] ?? '';
        if (is_array($tp)) {
            self::$trustProxyIps = array_values(array_filter(array_map('trim', $tp)));
        } else {
            self::$trustProxyIps = array_values(array_filter(array_map('trim', explode(',', (string) $tp))));
        }

        self::$sessionCookieDomain = trim((string) ($env['SESSION_COOKIE_DOMAIN'] ?? ''));
    }

    
    public static function allowedHosts(): array
    {
        return self::$allowedHosts;
    }

    
    public static function trustProxyIps(): array
    {
        return self::$trustProxyIps;
    }

    public static function sessionCookieDomain(): string
    {
        return self::$sessionCookieDomain;
    }
}
