<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Auth\Storage;

use Closure;
use Tradernet\Sdk\Auth\Session;
use Tradernet\Sdk\Auth\SessionLockInterface;
use Tradernet\Sdk\Auth\SessionStorageInterface;
use Tradernet\Sdk\Exception\ConfigurationException;

/**
 * File-backed session storage with flock and atomic rename.
 */
final class FileSessionStorage implements SessionStorageInterface
{
    /** @var array<string, int> Nesting depth of locks held by this process */
    private array $heldLocks = [];

    /**
     * @param string $directory Writable directory (created if missing)
     */
    public function __construct(
        private readonly string $directory,
    ) {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new ConfigurationException('Unable to create session directory: ' . $this->directory);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        $this->withLock($key, function () use ($key): void {
            $existing = $this->readFile($key);
            unset($existing['session']);
            if ($existing === []) {
                $path = $this->sessionPath($key);
                if (is_file($path)) {
                    unlink($path);
                }

                return;
            }

            $this->writeFile($key, $existing);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function load(string $key): ?Session
    {
        $this->assertSafeKey($key);
        $path = $this->sessionPath($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        /** @var mixed $data */
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['session']) || !is_array($data['session'])) {
            return null;
        }

        /** @var array<string, mixed> $sessionData */
        $sessionData = $data['session'];

        try {
            return Session::fromArray($sessionData);
        } catch (ConfigurationException) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function loadMeta(string $key): ?array
    {
        $this->assertSafeKey($key);
        $data = $this->readFile($key);
        if (!isset($data['meta']) || !is_array($data['meta'])) {
            return null;
        }

        /** @var array<string, mixed> $meta */
        $meta = $data['meta'];

        return $meta;
    }

    /**
     * {@inheritdoc}
     *
     * Re-entrant for the same process/key: nested {@see save()} / {@see saveMeta()}
     * during an outer {@see lock()} share the same flock. Cross-fiber use of the
     * same storage instance is not supported — serialize session I/O externally.
     */
    public function lock(string $key): SessionLockInterface
    {
        $this->assertSafeKey($key);

        if (isset($this->heldLocks[$key])) {
            ++$this->heldLocks[$key];
            $release = function () use ($key): void {
                if (!isset($this->heldLocks[$key])) {
                    return;
                }
                --$this->heldLocks[$key];
                if ($this->heldLocks[$key] <= 0) {
                    unset($this->heldLocks[$key]);
                }
            };

            return new class($release) implements SessionLockInterface {
                /**
                 * @param Closure(): void $release
                 */
                public function __construct(private readonly Closure $release) {}

                /**
                 * {@inheritdoc}
                 */
                public function unlock(): void
                {
                    ($this->release)();
                }
            };
        }

        $lockPath = $this->directory . '/' . $key . '.lock';
        $handle = fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new ConfigurationException('Unable to open lock file: ' . $lockPath);
        }
        chmod($lockPath, 0600);

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);

            throw new ConfigurationException('Unable to acquire session lock');
        }

        $this->heldLocks[$key] = 1;
        $release = function () use ($key, &$handle): void {
            if (!isset($this->heldLocks[$key])) {
                return;
            }
            --$this->heldLocks[$key];
            if ($this->heldLocks[$key] > 0) {
                return;
            }
            unset($this->heldLocks[$key]);
            if (is_resource($handle)) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
            $handle = null;
        };

        return new class($release) implements SessionLockInterface {
            /**
             * @param Closure(): void $release
             */
            public function __construct(private readonly Closure $release) {}

            /**
             * {@inheritdoc}
             */
            public function unlock(): void
            {
                ($this->release)();
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    public function save(string $key, Session $session): void
    {
        $this->withLock($key, function () use ($key, $session): void {
            $existing = $this->readFile($key);
            $existing['session'] = $session->toArray();
            $this->writeFile($key, $existing);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function saveMeta(string $key, array $meta): void
    {
        $this->withLock($key, function () use ($key, $meta): void {
            $existing = $this->readFile($key);
            $existing['meta'] = $meta;
            $this->writeFile($key, $existing);
        });
    }

    private function assertSafeKey(string $key): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $key) !== 1) {
            throw new ConfigurationException('Invalid session storage key');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readFile(string $key): array
    {
        $path = $this->sessionPath($key);
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }

        /** @var mixed $data */
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $k => $v) {
            if (is_string($k)) {
                $result[$k] = $v;
            }
        }

        return $result;
    }

    private function sessionPath(string $key): string
    {
        $this->assertSafeKey($key);

        return $this->directory . '/' . $key . '.json';
    }

    /**
     * @param callable(): void $callback
     */
    private function withLock(string $key, callable $callback): void
    {
        $lock = $this->lock($key);

        try {
            $callback();
        } finally {
            $lock->unlock();
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeFile(string $key, array $data): void
    {
        $path = $this->sessionPath($key);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        $handle = fopen($tmp, 'c');
        if ($handle === false) {
            throw new ConfigurationException('Unable to write session file');
        }

        // Restrict mode before any secret bytes are written (umask may create 0644).
        chmod($tmp, 0600);

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new ConfigurationException('Unable to lock temporary session file');
            }
            ftruncate($handle, 0);
            if (fwrite($handle, $json) === false) {
                throw new ConfigurationException('Unable to write session file');
            }
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if (!rename($tmp, $path)) {
            @unlink($tmp);

            throw new ConfigurationException('Unable to replace session file');
        }
        chmod($path, 0600);
    }
}
