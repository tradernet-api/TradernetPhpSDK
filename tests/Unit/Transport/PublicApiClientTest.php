<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Tradernet\Sdk\Exception\ConfigurationException;
use Tradernet\Sdk\Transport\PublicApiClient;

final class PublicApiClientTest extends TestCase
{
    public function testAcceptsAllowlistedHost(): void
    {
        $client = new PublicApiClient('pub', 'sec', 'https://tradernet.com/');
        self::assertInstanceOf(PublicApiClient::class, $client);
    }

    public function testRejectsHttpHost(): void
    {
        $this->expectException(ConfigurationException::class);

        new PublicApiClient('pub', 'sec', 'http://tradernet.com');
    }

    public function testRejectsUnknownHost(): void
    {
        $this->expectException(ConfigurationException::class);

        new PublicApiClient('pub', 'sec', 'https://evil.example');
    }
}
