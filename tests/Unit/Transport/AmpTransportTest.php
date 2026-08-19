<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Tradernet\Sdk\Exception\ConfigurationException;
use Tradernet\Sdk\Transport\AmpTransport;

final class AmpTransportTest extends TestCase
{
    public function testAcceptsAllowlistedHost(): void
    {
        $transport = new AmpTransport('pub', 'sec', 'https://tradernet.kz');
        self::assertInstanceOf(AmpTransport::class, $transport);
    }

    public function testRejectsUnknownHost(): void
    {
        $this->expectException(ConfigurationException::class);

        new AmpTransport('pub', 'sec', 'https://evil.example');
    }
}
