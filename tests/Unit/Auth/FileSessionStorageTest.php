<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tradernet\Sdk\Auth\Session;
use Tradernet\Sdk\Auth\Storage\FileSessionStorage;

final class FileSessionStorageTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/tn-sdk-sess-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        $files = glob($this->dir . '/*');
        if ($files === false) {
            $files = [];
        }
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testReentrantLockAllowsNestedSave(): void
    {
        $storage = new FileSessionStorage($this->dir);
        $key = hash('sha256', 'domain|login');
        $session = new Session(
            sid: 'abc',
            sidName: 'SID',
            userId: 1,
            login: 'login',
            createdAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+1 day'),
            domain: 'https://tradernet.com',
        );

        $outer = $storage->lock($key);

        try {
            $storage->save($key, $session);
            $storage->saveMeta($key, ['attempts' => 1]);
        } finally {
            $outer->unlock();
        }

        self::assertSame('abc', $storage->load($key)?->sid);
        self::assertSame(1, $storage->loadMeta($key)['attempts'] ?? null);
    }
}
