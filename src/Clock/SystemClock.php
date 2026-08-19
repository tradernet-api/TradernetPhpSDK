<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Clock;

/**
 * Wall-clock time via time().
 */
final class SystemClock implements ClockInterface
{
    /**
     * {@inheritdoc}
     */
    public function now(): int
    {
        return time();
    }
}
