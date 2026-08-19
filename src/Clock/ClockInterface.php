<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Clock;

/**
 * Injectable clock for SID TTL and reauth windows.
 */
interface ClockInterface
{
    /**
     * Current Unix timestamp in seconds.
     */
    public function now(): int;
}
