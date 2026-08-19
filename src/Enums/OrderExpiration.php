<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Enums;

use InvalidArgumentException;

/**
 * Order lifetime (day / extended / GTC).
 */
enum OrderExpiration: int
{
    case DAY = 1;
    case EXT = 2;
    case GTC = 3;

    /**
     * Resolves expiration from API name (day|ext|gtc).
     */
    public static function fromName(string $name): self
    {
        return match (strtolower($name)) {
            'day' => self::DAY,
            'ext' => self::EXT,
            'gtc' => self::GTC,
            default => throw new InvalidArgumentException('Unknown duration: ' . $name),
        };
    }
}
