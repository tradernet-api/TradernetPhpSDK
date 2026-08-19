<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Auth;

/**
 * No-op lock for in-memory / null storage.
 */
final class NullSessionLock implements SessionLockInterface
{
    /**
     * {@inheritdoc}
     */
    public function unlock(): void {}
}
