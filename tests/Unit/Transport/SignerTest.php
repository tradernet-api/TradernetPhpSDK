<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Tradernet\Sdk\Transport\Signer;

final class SignerTest extends TestCase
{
    public function testSignIsHmacSha256Hex(): void
    {
        $signer = new Signer('secret');
        $expected = hash_hmac('sha256', 'payload123', 'secret');

        self::assertSame($expected, $signer->sign('payload123'));
    }

    public function testStringifyCompactJson(): void
    {
        $signer = new Signer('secret');

        self::assertSame('{"a":1,"b":"x"}', $signer->stringify(['a' => 1, 'b' => 'x']));
        self::assertSame('', $signer->stringify([]));
    }
}
