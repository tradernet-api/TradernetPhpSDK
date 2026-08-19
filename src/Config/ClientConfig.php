<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Config;

/**
 * Client runtime configuration.
 */
final readonly class ClientConfig
{
    public const int DEFAULT_SID_TTL_SECONDS = 1_209_600; // 14 days

    /**
     * Cloudflare in front of Tradernet rejects known HTTP-library agents
     * (amphp/http-client gets 403 on the WebSocket handshake), so the SDK
     * always identifies itself instead of letting a client library do it.
     */
    public const string DEFAULT_USER_AGENT = 'tradernet-php-sdk/0.1';

    public string $domain;

    /**
     * @param string $domain Base HTTPS host (allowlisted Tradernet cabinet)
     * @param string $lang Language cookie
     * @param int $sidTtlSeconds SID local TTL
     * @param null|string $sessionPath Directory for file session storage
     * @param string $sidCookieName SID or SIDBETA
     * @param float $timeout Request timeout
     * @param int $reauthMaxAttempts Max authByLogin attempts per window
     * @param int $reauthWindowSeconds Sliding window for reauth attempts
     * @param int $reauthOpenSeconds Circuit open duration after limit
     * @param string $userAgent User-Agent for HTTP and WebSocket requests
     */
    public function __construct(
        string $domain = 'https://tradernet.com',
        public string $lang = 'en',
        public AuthMode $authMode = AuthMode::SID_LAZY,
        public int $sidTtlSeconds = self::DEFAULT_SID_TTL_SECONDS,
        public ?string $sessionPath = null,
        public string $sidCookieName = 'SID',
        public float $timeout = 30.0,
        public int $reauthMaxAttempts = 3,
        public int $reauthWindowSeconds = 900,
        public int $reauthOpenSeconds = 900,
        public string $userAgent = self::DEFAULT_USER_AGENT,
    ) {
        $this->domain = Domain::normalize($domain);
    }

    /**
     * Host without scheme for WebSocket subdomain.
     */
    public function host(): string
    {
        $host = parse_url($this->domain, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'tradernet.com';
    }

    /**
     * WebSocket base URL without query.
     */
    public function websocketUrl(): string
    {
        return 'wss://wss.' . $this->host() . '/';
    }
}
