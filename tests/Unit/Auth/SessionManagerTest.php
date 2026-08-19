<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Tradernet\Sdk\Auth\ReauthGuard;
use Tradernet\Sdk\Auth\SessionManager;
use Tradernet\Sdk\Auth\Storage\InMemorySessionStorage;
use Tradernet\Sdk\Clock\FixedClock;
use Tradernet\Sdk\Config\AuthMode;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Config\Credentials;
use Tradernet\Sdk\Exception\ApiErrorException;
use Tradernet\Sdk\Exception\AuthenticationException;
use Tradernet\Sdk\Exception\ReauthBlockedException;
use Tradernet\Sdk\Transport\FakeTransport;

final class SessionManagerTest extends TestCase
{
    public function testAdoptFromResponsePersistsSmsSession(): void
    {
        $transport = new FakeTransport();
        $manager = $this->manager($transport);

        $session = $manager->adoptFromResponse(
            ['SID' => 'sms-sid', 'userId' => 9],
            '+77001234567',
        );

        self::assertSame('sms-sid', $session->sid);
        self::assertSame('sms-sid', $manager->current()?->sid);
    }

    public function testAuthenticateStoresSession(): void
    {
        $transport = new FakeTransport();
        $transport->enqueue(['SID' => 'abc123', 'userId' => 42]);

        $manager = $this->manager($transport);
        $session = $manager->authenticate();

        self::assertSame('abc123', $session->sid);
        self::assertSame(42, $session->userId);
        self::assertSame('authByLogin', $transport->calls[0]['command']);
        self::assertFalse($transport->calls[0]['attachSid']);
    }

    public function testEnsureSessionReusesStorage(): void
    {
        $transport = new FakeTransport();
        $transport->enqueue(['SID' => 's1', 'userId' => 1]);

        $manager = $this->manager($transport);
        $first = $manager->authenticate();
        $second = $manager->ensureSession(true);

        self::assertNotNull($second);
        self::assertSame($first->sid, $second->sid);
        self::assertCount(1, $transport->calls);
    }

    public function testLoginWithPasswordPersistsSession(): void
    {
        $transport = new FakeTransport();
        $transport->enqueue(['SID' => 'from-login', 'userId' => 7]);

        $manager = $this->manager($transport);
        $response = $manager->loginWithPassword('other@example.com', 'secret');

        self::assertSame('from-login', $response['SID']);
        $current = $manager->current();
        self::assertNotNull($current);
        self::assertSame('from-login', $current->sid);
        self::assertSame('other@example.com', $current->login);
    }

    public function testNestedReauthBlocked(): void
    {
        $storage = new InMemorySessionStorage();
        $clock = new FixedClock(1);
        $guard = new ReauthGuard($storage, new ClientConfig(), $clock);
        $guard->begin();

        $this->expectException(ReauthBlockedException::class);
        $guard->begin();
    }

    public function testPermanentFailureOpensCircuit(): void
    {
        $transport = new FakeTransport();
        $transport->enqueueException(new ApiErrorException('Invalid password', 1));

        $storage = new InMemorySessionStorage();
        $clock = new FixedClock(1_000);
        $credentials = new Credentials('k', 's', 'user', 'bad');
        $config = new ClientConfig(authMode: AuthMode::SID_LAZY, reauthMaxAttempts: 3);
        $guard = new ReauthGuard($storage, $config, $clock);
        $manager = new SessionManager($credentials, $config, $transport, $storage, $guard, $clock);

        try {
            $manager->authenticate();
            self::fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            self::assertTrue($e->isPermanent());
        }

        $this->expectException(ReauthBlockedException::class);
        $manager->authenticate();
    }

    public function testReauthGuardBlocksAfterMaxAttempts(): void
    {
        $storage = new InMemorySessionStorage();
        $clock = new FixedClock(1_000_000);
        $config = new ClientConfig(
            authMode: AuthMode::SID_LAZY,
            reauthMaxAttempts: 3,
            reauthWindowSeconds: 900,
        );
        $guard = new ReauthGuard($storage, $config, $clock);
        $key = 'test-key';

        $guard->recordAttempt($key);
        $guard->recordAttempt($key);
        $guard->recordAttempt($key);

        $this->expectException(ReauthBlockedException::class);
        $guard->allow($key);
    }

    private function manager(FakeTransport $transport): SessionManager
    {
        $storage = new InMemorySessionStorage();
        $clock = new FixedClock(1_700_000_000);
        $credentials = new Credentials('k', 's', 'user@example.com', 'pass');
        $config = new ClientConfig(authMode: AuthMode::SID_LAZY);
        $guard = new ReauthGuard($storage, $config, $clock);

        return new SessionManager($credentials, $config, $transport, $storage, $guard, $clock);
    }
}
