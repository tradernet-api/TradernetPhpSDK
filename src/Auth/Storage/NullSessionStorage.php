<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Auth\Storage;

use Tradernet\Sdk\Auth\NullSessionLock;
use Tradernet\Sdk\Auth\Session;
use Tradernet\Sdk\Auth\SessionLockInterface;
use Tradernet\Sdk\Auth\SessionStorageInterface;

/**
 * Discards sessions (no persistence).
 */
final class NullSessionStorage implements SessionStorageInterface
{
    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void {}

    /**
     * {@inheritdoc}
     */
    public function load(string $key): ?Session
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function loadMeta(string $key): ?array
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function lock(string $key): SessionLockInterface
    {
        return new NullSessionLock();
    }

    /**
     * {@inheritdoc}
     */
    public function save(string $key, Session $session): void {}

    /**
     * {@inheritdoc}
     */
    public function saveMeta(string $key, array $meta): void {}
}
