<?php

declare(strict_types=1);

class Validator
{
    public static function text(?string $value, int $maxLength = 255): string
    {
        $value = trim((string) $value);
        if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }

        return $value;
    }

    public static function textarea(?string $value, int $maxLength = 2000, bool $stripTags = false): string
    {
        $value = trim((string) $value);
        if ($stripTags) {
            $value = strip_tags($value);
        }

        if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }

        return $value;
    }

    public static function email(?string $value): string
    {
        return trim(mb_strtolower((string) $value));
    }

    public static function enum(?string $value, array $allowed, string $default): string
    {
        $value = (string) $value;
        return in_array($value, $allowed, true) ? $value : $default;
    }

    public static function intArray(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $ints = array_map(static fn($value): int => (int) $value, $values);
        $ints = array_filter($ints, static fn(int $value): bool => $value > 0);

        return array_values(array_unique($ints));
    }
}
