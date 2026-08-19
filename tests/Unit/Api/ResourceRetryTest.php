<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Tradernet\Sdk\Auth\Storage\InMemorySessionStorage;
use Tradernet\Sdk\Clock\FixedClock;
use Tradernet\Sdk\Config\AuthMode;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Config\Credentials;
use Tradernet\Sdk\Exception\ApiErrorException;
use Tradernet\Sdk\Tradernet;
use Tradernet\Sdk\Transport\FakeTransport;

final class ResourceRetryTest extends TestCase
{
    public function testBuyMapsToPutTradeOrder(): void
    {
        $transport = new FakeTransport();
        $transport->enqueue(['order_id' => 1]);

        $tn = $this->client($transport, AuthMode::KEYS_ONLY);
        $tn->orders()->buy('AAPL.US', 2, 100.5);

        self::assertSame('putTradeOrder', $transport->calls[0]['command']);
        self::assertSame('AAPL.US', $transport->calls[0]['data']['instr_name']);
        self::assertSame(1, $transport->calls[0]['data']['action_id']);
        self::assertSame(2, $transport->calls[0]['data']['order_type_id']);
        self::assertFalse($transport->calls[0]['attachSid']);
    }

    public function testDeadSidTriggersSingleReauthOnSidRequiredCommand(): void
    {
        $transport = new FakeTransport();
        $transport->enqueue(['SID' => 'old-sid', 'userId' => 1]);
        $transport->enqueueException(new ApiErrorException('SID expired', 7, [], 401));
        $transport->enqueue(['SID' => 'new-sid', 'userId' => 1]);
        $transport->enqueue(['user' => ['id' => 1]]);

        $tn = $this->client($transport);
        $tn->sessions()->authenticate();

        $result = $tn->user()->info();

        self::assertArrayHasKey('user', $result);

        $commands = array_column($transport->calls, 'command');
        self::assertSame('authByLogin', $commands[0]);
        self::assertSame('getUserInfo', $commands[1]);
        self::assertSame('authByLogin', $commands[2]);
        self::assertSame('getUserInfo', $commands[3]);
        self::assertCount(4, $transport->calls);
        self::assertTrue($transport->calls[1]['attachSid']);
        self::assertTrue($transport->calls[3]['attachSid']);
    }

    public function testQuotesDoesNotAttachSidEvenWhenSessionExists(): void
    {
        $transport = new FakeTransport();
        $transport->enqueue(['SID' => 'old-sid', 'userId' => 1]);
        $transport->enqueue(['quotes' => [['ticker' => 'AAPL.US']]]);

        $tn = $this->client($transport);
        $tn->sessions()->authenticate();
        $tn->quotes()->get(['AAPL.US']);

        self::assertSame('getStockQuotesJson', $transport->calls[1]['command']);
        self::assertFalse($transport->calls[1]['attachSid']);
        self::assertNull($transport->calls[1]['session']);
    }

    public function testSecuritySessionErrorDoesNotTriggerReauthOrRetry(): void
    {
        $transport = new FakeTransport();
        $transport->enqueue(['SID' => 'old-sid', 'userId' => 1]);
        $transport->enqueueException(
            new ApiErrorException('you need to open a security session', null, [], 403),
        );

        $tn = $this->client($transport);
        $tn->sessions()->authenticate();

        $this->expectException(ApiErrorException::class);

        try {
            $tn->user()->info();
        } finally {
            $commands = array_column($transport->calls, 'command');
            self::assertSame(['authByLogin', 'getUserInfo'], $commands);
        }
    }

    private function client(FakeTransport $transport, AuthMode $mode = AuthMode::SID_LAZY): Tradernet
    {
        $credentials = new Credentials('k', 's', 'user', 'pass');
        $config = new ClientConfig(authMode: $mode);
        $storage = new InMemorySessionStorage();
        $clock = new FixedClock(1_700_000_000);

        return new Tradernet($credentials, $config, $transport, $storage, $clock);
    }
}
