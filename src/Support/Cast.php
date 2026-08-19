<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Support;

/**
 * Safe casts for mixed session/JSON/config values.
 */
final class Cast
{
    /**
     * Cast a mixed value to int, or return $default when not numeric.
     */
    public static function int(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Cast a mixed value to string, or return $default when not scalar-numeric.
     */
    public static function string(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Cast to a non-empty string, or null when missing/empty.
     */
    public static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $string = self::string($value);

        return $string === '' ? null : $string;
    }
}
