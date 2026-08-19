<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Config;

use Closure;
use JsonSerializable;
use SensitiveParameter;
use Tradernet\Sdk\Exception\ConfigurationException;

/**
 * API credentials and optional login/password for SID.
 *
 * Properties are private so `json_encode($credentials)` cannot leak secrets.
 */
final class Credentials implements JsonSerializable
{
    /** @var null|Closure(): string */
    private readonly ?Closure $passwordProvider;

    /**
     * @param string $apiKey Public API key
     * @param string $apiSecret Private API key
     * @param null|string $login Login for authByLogin
     * @param null|Closure|string $password Password string or provider closure
     */
    public function __construct(
        #[SensitiveParameter]
        private readonly string $apiKey,
        #[SensitiveParameter]
        private readonly string $apiSecret,
        private readonly ?string $login = null,
        #[SensitiveParameter]
        Closure|string|null $password = null,
    ) {
        if ($apiKey === '' || $apiSecret === '') {
            throw new ConfigurationException('API key and secret are required');
        }

        if ($password instanceof Closure) {
            $this->passwordProvider = $password;
        } elseif (is_string($password)) {
            $this->passwordProvider = static fn (): string => $password;
        } else {
            $this->passwordProvider = null;
        }
    }

    /**
     * @return array<string, null|string>
     */
    public function __debugInfo(): array
    {
        return [
            'apiKey' => '***',
            'apiSecret' => '***',
            'login' => $this->login,
            'password' => $this->passwordProvider !== null ? '***' : null,
        ];
    }

    /**
     * @throws ConfigurationException Always thrown; credentials must not be serialized
     */
    public function __serialize(): never
    {
        throw new ConfigurationException('Credentials must not be serialized');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws ConfigurationException Always thrown; credentials must not be unserialized
     */
    public function __unserialize(array $data): never
    {
        throw new ConfigurationException('Credentials must not be unserialized');
    }

    /**
     * Public API key.
     */
    public function apiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Private API key used for HMAC signing.
     */
    public function apiSecret(): string
    {
        return $this->apiSecret;
    }

    /**
     * Whether login/password pair is configured.
     */
    public function hasLoginPassword(): bool
    {
        return $this->login !== null
            && $this->login !== ''
            && $this->passwordProvider !== null;
    }

    /**
     * @return array<string, null|string>
     */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    /**
     * Login used for authByLogin, if configured.
     */
    public function login(): ?string
    {
        return $this->login;
    }

    /**
     * Resolves password value.
     *
     * @throws ConfigurationException
     */
    public function password(): string
    {
        if ($this->passwordProvider === null) {
            throw new ConfigurationException('Password is not configured');
        }

        $password = ($this->passwordProvider)();
        if (!is_string($password) || $password === '') {
            throw new ConfigurationException('Password provider returned an empty string');
        }

        return $password;
    }
}
