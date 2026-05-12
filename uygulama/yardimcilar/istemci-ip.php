<?php

declare(strict_types=1);

final class ClientIp
{
    public static function get(): string
    {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        if ($remote !== '' && self::isTrustedProxy($remote)) {
            $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if (is_string($xff) && $xff !== '') {
                $parts = array_map('trim', explode(',', $xff));
                $first = $parts[0] ?? '';
                if ($first !== '' && filter_var($first, FILTER_VALIDATE_IP) !== false) {
                    return self::normalize($first);
                }
            }
        }

        return self::normalize($remote !== '' ? $remote : null);
    }

    public static function normalize(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return '0.0.0.0';
        }
        if ($ip === '::1') {
            return '127.0.0.1';
        }
        if (str_starts_with($ip, '::ffff:')) {
            $v4 = substr($ip, 7);
            if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $v4;
            }
        }
        return $ip;
    }

    private static function isTrustedProxy(string $remoteAddr): bool
    {
        $trusted = AppConfig::trustProxyIps();
        if ($trusted === []) {
            return false;
        }
        return in_array($remoteAddr, $trusted, true);
    }
}
