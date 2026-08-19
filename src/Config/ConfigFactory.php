<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Config;

use Tradernet\Sdk\Exception\ConfigurationException;
use Tradernet\Sdk\Support\Cast;

/**
 * Builds Credentials and ClientConfig from env / ini / array.
 */
final class ConfigFactory
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array{0: Credentials, 1: ClientConfig}
     */
    public static function fromArray(array $data): array
    {
        $apiKey = Cast::string($data['api_key'] ?? $data['public'] ?? '');
        $apiSecret = Cast::string($data['api_secret'] ?? $data['private'] ?? '');
        $credentials = new Credentials(
            $apiKey,
            $apiSecret,
            Cast::stringOrNull($data['login'] ?? null),
            Cast::stringOrNull($data['password'] ?? null),
        );

        $authMode = AuthMode::SID_LAZY;
        if (isset($data['auth_mode'])) {
            $mode = AuthMode::tryFrom(Cast::string($data['auth_mode']));
            if ($mode === null) {
                throw new ConfigurationException('Invalid auth_mode');
            }
            $authMode = $mode;
        }

        $config = new ClientConfig(
            domain: Cast::string($data['domain'] ?? 'https://tradernet.com', 'https://tradernet.com'),
            lang: Cast::string($data['lang'] ?? 'en', 'en'),
            authMode: $authMode,
            sessionPath: Cast::stringOrNull($data['session_path'] ?? null),
            sidCookieName: Cast::string($data['sid_cookie'] ?? 'SID', 'SID'),
        );

        return [$credentials, $config];
    }

    /**
     * @param null|array<string, null|string> $env
     *
     * @return array{0: Credentials, 1: ClientConfig}
     */
    public static function fromEnv(?array $env = null): array
    {
        $get = static function (string $key) use ($env): ?string {
            if ($env !== null) {
                $value = $env[$key] ?? null;
                if ($value === null || $value === '') {
                    return null;
                }

                return $value;
            }

            if (array_key_exists($key, $_ENV) && is_string($_ENV[$key]) && $_ENV[$key] !== '') {
                return $_ENV[$key];
            }
            if (array_key_exists($key, $_SERVER) && is_string($_SERVER[$key]) && $_SERVER[$key] !== '') {
                return $_SERVER[$key];
            }

            $value = getenv($key);
            if (!is_string($value) || $value === '') {
                return null;
            }

            return $value;
        };

        $apiKey = $get('TRADERNET_API_KEY');
        $apiSecret = $get('TRADERNET_API_SECRET');
        if ($apiKey === null || $apiSecret === null) {
            throw new ConfigurationException(
                'TRADERNET_API_KEY and TRADERNET_API_SECRET are required. '
                . 'Export them, load a .env file in your application '
                . '(the SDK never loads .env itself), or use Tradernet::fromIni().',
            );
        }

        $credentials = new Credentials(
            $apiKey,
            $apiSecret,
            $get('TRADERNET_LOGIN'),
            $get('TRADERNET_PASSWORD'),
        );

        $authModeRaw = $get('TRADERNET_AUTH_MODE') ?? AuthMode::SID_LAZY->value;
        $authMode = AuthMode::tryFrom($authModeRaw);
        if ($authMode === null) {
            throw new ConfigurationException('Invalid TRADERNET_AUTH_MODE: ' . $authModeRaw);
        }

        $config = new ClientConfig(
            domain: $get('TRADERNET_DOMAIN') ?? 'https://tradernet.com',
            lang: $get('TRADERNET_LANG') ?? 'en',
            authMode: $authMode,
            sessionPath: $get('TRADERNET_SESSION_PATH'),
            sidCookieName: $get('TRADERNET_SID_COOKIE') ?? 'SID',
        );

        return [$credentials, $config];
    }

    /**
     * Reads [auth] section from tradernet.ini (Python SDK parity).
     *
     * @return array{0: Credentials, 1: ClientConfig}
     */
    public static function fromIni(string $path): array
    {
        if (!is_file($path)) {
            throw new ConfigurationException('Config file not found: ' . $path);
        }

        $parsed = parse_ini_file($path, true, INI_SCANNER_RAW);
        if ($parsed === false) {
            throw new ConfigurationException('Unable to parse INI: ' . $path);
        }

        /** @var array<string, mixed> $auth */
        $auth = is_array($parsed['auth'] ?? null) ? $parsed['auth'] : [];
        $public = Cast::string($auth['public'] ?? '');
        $private = Cast::string($auth['private'] ?? '');
        if ($public === '' || $private === '') {
            throw new ConfigurationException('INI [auth] requires public and private keys');
        }

        $login = Cast::stringOrNull($auth['login'] ?? null);
        $password = Cast::stringOrNull($auth['password'] ?? null);

        $credentials = new Credentials($public, $private, $login, $password);

        $authMode = AuthMode::SID_LAZY;
        if (isset($auth['auth_mode'])) {
            $mode = AuthMode::tryFrom(Cast::string($auth['auth_mode']));
            if ($mode === null) {
                throw new ConfigurationException('Invalid auth_mode in INI');
            }
            $authMode = $mode;
        }

        $config = new ClientConfig(
            domain: Cast::stringOrNull($auth['domain'] ?? null) ?? 'https://tradernet.com',
            lang: Cast::string($auth['lang'] ?? 'en', 'en'),
            authMode: $authMode,
            sessionPath: Cast::stringOrNull($auth['session_path'] ?? null),
            sidCookieName: Cast::string($auth['sid_cookie'] ?? 'SID', 'SID'),
        );

        return [$credentials, $config];
    }
}
