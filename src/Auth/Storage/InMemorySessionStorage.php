<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Auth\Storage;

use Tradernet\Sdk\Auth\NullSessionLock;
use Tradernet\Sdk\Auth\Session;
use Tradernet\Sdk\Auth\SessionLockInterface;
use Tradernet\Sdk\Auth\SessionStorageInterface;

/**
 * In-memory session storage for tests and short-lived processes.
 */
final class InMemorySessionStorage implements SessionStorageInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $meta = [];
    /** @var array<string, Session> */
    private array $sessions = [];

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        unset($this->sessions[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function load(string $key): ?Session
    {
        return $this->sessions[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function loadMeta(string $key): ?array
    {
        return $this->meta[$key] ?? null;
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
    public function save(string $key, Session $session): void
    {
        $this->sessions[$key] = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function saveMeta(string $key, array $meta): void
    {
        $this->meta[$key] = $meta;
    }
}
