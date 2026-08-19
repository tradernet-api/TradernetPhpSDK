<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Config\Domain;
use Tradernet\Sdk\Exception\ConfigurationException;

final class DomainTest extends TestCase
{
    public function testAllowsSubdomain(): void
    {
        self::assertSame(
            'https://beta.tradernet.com',
            Domain::normalize('https://beta.tradernet.com'),
        );
    }

    public function testClientConfigNormalizesDomain(): void
    {
        $config = new ClientConfig(domain: 'https://freedom24.com/');
        self::assertSame('https://freedom24.com', $config->domain);
    }

    public function testNormalizesAllowedHost(): void
    {
        self::assertSame(
            'https://tradernet.global',
            Domain::normalize('https://tradernet.global/'),
        );
    }

    public function testRejectsHttp(): void
    {
        $this->expectException(ConfigurationException::class);
        Domain::normalize('http://tradernet.com');
    }

    public function testRejectsPath(): void
    {
        $this->expectException(ConfigurationException::class);
        Domain::normalize('https://tradernet.com/api');
    }

    public function testRejectsUnknownHost(): void
    {
        $this->expectException(ConfigurationException::class);
        Domain::normalize('https://evil.example');
    }
}
