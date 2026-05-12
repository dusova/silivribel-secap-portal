<?php

declare(strict_types=1);

final class RequestGuard
{
    public static function enforceTrustedHost(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $allowed = AppConfig::allowedHosts();
        if ($allowed === []) {
            return;
        }

        $raw = $_SERVER['HTTP_HOST'] ?? '';
        if ($raw === '') {
            return;
        }

        $host = self::hostWithoutPort(strtolower($raw));

        if (!in_array($host, $allowed, true)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            exit('Bu uygulama yalnızca tanımlı şirket içi adreslerden erişilebilir.');
        }
    }

    private static function hostWithoutPort(string $httpHost): string
    {
        if ($httpHost === '') {
            return '';
        }
        if (str_starts_with($httpHost, '[')) {
            $end = strpos($httpHost, ']');
            if ($end !== false) {
                return substr($httpHost, 1, $end - 1);
            }
        }
        $colon = strrpos($httpHost, ':');
        if ($colon !== false) {
            $tail = substr($httpHost, $colon + 1);
            if ($tail !== '' && ctype_digit($tail)) {
                return substr($httpHost, 0, $colon);
            }
        }
        return $httpHost;
    }
}
