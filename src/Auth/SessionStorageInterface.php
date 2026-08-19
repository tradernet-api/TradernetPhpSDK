<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Auth;

interface SessionStorageInterface
{
    /**
     * Removes the persisted session for key (meta may remain).
     */
    public function delete(string $key): void;

    /**
     * @param string $key Storage key
     */
    public function load(string $key): ?Session;

    /**
     * Load reauth guard state for key.
     *
     * @return null|array<string, mixed>
     */
    public function loadMeta(string $key): ?array;

    /**
     * Acquire exclusive lock for re-auth coordination.
     */
    public function lock(string $key): SessionLockInterface;

    /**
     * Persist SID session under key.
     */
    public function save(string $key, Session $session): void;

    /**
     * Persist reauth guard state.
     *
     * @param array<string, mixed> $meta
     */
    public function saveMeta(string $key, array $meta): void;
}
