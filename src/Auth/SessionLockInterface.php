<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Auth;

/**
 * Lock handle released via unlock().
 */
interface SessionLockInterface
{
    /**
     * Releases the acquired lock.
     */
    public function unlock(): void;
}
