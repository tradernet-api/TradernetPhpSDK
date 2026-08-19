<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Tradernet\Sdk\Config\AuthMode;
use Tradernet\Sdk\Config\ConfigFactory;
use Tradernet\Sdk\Config\Credentials;
use Tradernet\Sdk\Exception\ConfigurationException;

final class CredentialsTest extends TestCase
{
    public function testCannotSerialize(): void
    {
        $c = new Credentials('pub', 'priv');
        $this->expectException(ConfigurationException::class);
        serialize($c);
    }

    public function testMasksSecretsInDebugInfo(): void
    {
        $c = new Credentials('pub', 'priv', 'user@example.com', 'secret');
        $info = $c->__debugInfo();

        self::assertSame('***', $info['apiKey']);
        self::assertSame('***', $info['apiSecret']);
        self::assertSame('***', $info['password']);
        self::assertSame('user@example.com', $info['login']);
    }

    public function testPasswordProvider(): void
    {
        $c = new Credentials('pub', 'priv', 'u', static fn (): string => 'from-vault');
        self::assertTrue($c->hasLoginPassword());
        self::assertSame('from-vault', $c->password());
    }
}

final class ConfigFactoryTest extends TestCase
{
    public function testFromEnv(): void
    {
        [$creds, $config] = ConfigFactory::fromEnv([
            'TRADERNET_API_KEY' => 'k',
            'TRADERNET_API_SECRET' => 's',
            'TRADERNET_LOGIN' => 'l',
            'TRADERNET_PASSWORD' => 'p',
            'TRADERNET_AUTH_MODE' => 'keys_only',
            'TRADERNET_DOMAIN' => 'https://tradernet.global',
        ]);

        self::assertSame('k', $creds->apiKey());
        self::assertTrue($creds->hasLoginPassword());
        self::assertSame(AuthMode::KEYS_ONLY, $config->authMode);
        self::assertSame('https://tradernet.global', $config->domain);
    }

    public function testFromIni(): void
    {
        $path = sys_get_temp_dir() . '/tradernet-sdk-test.ini';
        file_put_contents($path, "[auth]\npublic=pk\nprivate=sk\nlogin=me\npassword=pw\n");

        [$creds, $config] = ConfigFactory::fromIni($path);
        self::assertSame('pk', $creds->apiKey());
        self::assertSame('me', $creds->login());
        self::assertSame('pw', $creds->password());
        unlink($path);
    }

    public function testJsonEncodeDoesNotLeakSecrets(): void
    {
        $creds = new Credentials('pub', 'priv', 'user@example.com', 'secret');
        $json = json_encode($creds, JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('priv', $json);
        self::assertStringNotContainsString('secret', $json);
        self::assertStringContainsString('***', $json);
    }
}
