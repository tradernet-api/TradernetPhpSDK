<?php

declare(strict_types=1);

namespace Tradernet\Sdk;

use Tradernet\Sdk\Api\AbstractResource;
use Tradernet\Sdk\Api\AlertsApi;
use Tradernet\Sdk\Api\AuthApi;
use Tradernet\Sdk\Api\CpsApi;
use Tradernet\Sdk\Api\CurrencyApi;
use Tradernet\Sdk\Api\NewsApi;
use Tradernet\Sdk\Api\OrdersApi;
use Tradernet\Sdk\Api\PortfolioApi;
use Tradernet\Sdk\Api\QuotesApi;
use Tradernet\Sdk\Api\ReferenceApi;
use Tradernet\Sdk\Api\ReportsApi;
use Tradernet\Sdk\Api\SecuritySessionApi;
use Tradernet\Sdk\Api\ShopApi;
use Tradernet\Sdk\Api\StockListsApi;
use Tradernet\Sdk\Api\TariffApi;
use Tradernet\Sdk\Api\UserApi;
use Tradernet\Sdk\Auth\ReauthGuard;
use Tradernet\Sdk\Auth\SessionManager;
use Tradernet\Sdk\Auth\SessionStorageInterface;
use Tradernet\Sdk\Auth\Storage\FileSessionStorage;
use Tradernet\Sdk\Auth\Storage\InMemorySessionStorage;
use Tradernet\Sdk\Clock\ClockInterface;
use Tradernet\Sdk\Clock\SystemClock;
use Tradernet\Sdk\Config\AuthMode;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Config\ConfigFactory;
use Tradernet\Sdk\Config\Credentials;
use Tradernet\Sdk\Exception\ConfigurationException;
use Tradernet\Sdk\Transport\HttpMethod;
use Tradernet\Sdk\Transport\PublicApiClient;
use Tradernet\Sdk\Transport\TransportInterface;
use Tradernet\Sdk\Ws\Amp\AmpWebSocketClient;
use Tradernet\Sdk\Ws\WebSocketClientInterface;

/**
 * Tradernet SDK facade.
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class Tradernet
{
    private ?AlertsApi $alerts = null;

    private ?AuthApi $auth = null;

    private ClientConfig $config;

    private ?CpsApi $cps = null;

    private ?CurrencyApi $currency = null;

    private ?NewsApi $news = null;

    private ?OrdersApi $orders = null;

    private ?PortfolioApi $portfolio = null;

    private ?QuotesApi $quotes = null;

    private ?ReferenceApi $reference = null;

    private ?ReportsApi $reports = null;

    private ?SecuritySessionApi $securitySessions = null;

    private SessionManager $sessions;

    private ?ShopApi $shop = null;

    private ?StockListsApi $stockLists = null;

    private ?TariffApi $tariff = null;
    private TransportInterface $transport;

    private ?UserApi $user = null;

    /**
     * @param Credentials $credentials API key pair (+ optional login/password for SID)
     * @param null|ClientConfig $config Domain, auth mode, timeouts (defaults apply)
     * @param null|TransportInterface $transport Override HTTP transport (tests / Amp)
     * @param null|SessionStorageInterface $storage SID persistence (default: files under ~/.tradernet)
     * @param null|ClockInterface $clock Injectable clock (tests)
     */
    public function __construct(
        Credentials $credentials,
        ?ClientConfig $config = null,
        ?TransportInterface $transport = null,
        ?SessionStorageInterface $storage = null,
        ?ClockInterface $clock = null,
    ) {
        $this->config = $config ?? new ClientConfig();
        $clock ??= new SystemClock();
        $this->transport = $transport ?? new PublicApiClient(
            $credentials->apiKey(),
            $credentials->apiSecret(),
            $this->config->domain,
            $clock,
            null,
            $this->config->timeout,
            $this->config->userAgent,
        );

        if ($this->transport instanceof PublicApiClient) {
            $this->transport->setLanguage($this->config->lang);
        }

        $storage ??= $this->createDefaultStorage();
        $reauth = new ReauthGuard($storage, $this->config, $clock);
        $this->sessions = new SessionManager(
            $credentials,
            $this->config,
            $this->transport,
            $storage,
            $reauth,
            $clock,
        );

        if ($this->config->authMode === AuthMode::SID_EAGER) {
            $this->sessions->ensureSession(true);
        }
    }

    /**
     * Price alerts resource.
     */
    public function alerts(): AlertsApi
    {
        return $this->alerts ??= new AlertsApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * Authorization resource.
     */
    public function auth(): AuthApi
    {
        return $this->auth ??= new AuthApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * Runtime client configuration.
     */
    public function config(): ClientConfig
    {
        return $this->config;
    }

    /**
     * CPS instructions resource.
     */
    public function cps(): CpsApi
    {
        return $this->cps ??= new CpsApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * Currency / cross-rates resource.
     */
    public function currency(): CurrencyApi
    {
        return $this->currency ??= new CurrencyApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * News resource.
     */
    public function news(): NewsApi
    {
        return $this->news ??= new NewsApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * Orders resource.
     */
    public function orders(): OrdersApi
    {
        return $this->orders ??= new OrdersApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * Portfolio resource.
     */
    public function portfolio(): PortfolioApi
    {
        return $this->portfolio ??= new PortfolioApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * Quotes resource.
     */
    public function quotes(): QuotesApi
    {
        return $this->quotes ??= new QuotesApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * Reference data resource.
     */
    public function reference(): ReferenceApi
    {
        return $this->reference ??= new ReferenceApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * Broker / depositary reports resource.
     */
    public function reports(): ReportsApi
    {
        return $this->reports ??= new ReportsApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * Escape hatch for any V3 command.
     *
     * SID is attached only when `$requiresSid` is true (same rule as typed resources).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function request(
        string $command,
        array $data = [],
        HttpMethod $method = HttpMethod::POST,
        bool $requiresSid = false,
    ): array {
        $runner = new class($this->transport, $this->sessions, $this->config) extends AbstractResource {
            protected function requiresSid(): bool
            {
                return false;
            }

            /**
             * @param array<string, mixed> $data
             *
             * @return array<string, mixed>
             */
            public function run(
                string $command,
                array $data,
                HttpMethod $method,
                bool $requiresSid,
            ): array {
                return $this->call($command, $data, $method, $requiresSid);
            }
        };

        return $runner->run($command, $data, $method, $requiresSid);
    }

    /**
     * Security (trading) session resource.
     */
    public function securitySessions(): SecuritySessionApi
    {
        return $this->securitySessions ??= new SecuritySessionApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * SID session manager.
     */
    public function sessions(): SessionManager
    {
        return $this->sessions;
    }

    /**
     * Shop / IPO resource.
     */
    public function shop(): ShopApi
    {
        return $this->shop ??= new ShopApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * Watchlists / stock lists resource.
     */
    public function stockLists(): StockListsApi
    {
        return $this->stockLists ??= new StockListsApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * Tariff resource.
     */
    public function tariff(): TariffApi
    {
        return $this->tariff ??= new TariffApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * Low-level HTTP transport.
     */
    public function transport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * User / profile resource.
     */
    public function user(): UserApi
    {
        return $this->user ??= new UserApi($this->transport, $this->sessions, $this->config);
    }

    /**
     * Creates Amp WebSocket client (requires amphp/websocket-client).
     */
    public function websocket(bool $requireSid = true): WebSocketClientInterface
    {
        return new AmpWebSocketClient($this->config, $this->sessions, $requireSid);
    }

    /**
     * Bootstrap from associative array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        [$credentials, $config] = ConfigFactory::fromArray($data);

        return new self($credentials, $config);
    }

    /**
     * Bootstrap from environment variables.
     *
     * @param null|array<string, null|string> $env
     */
    public static function fromEnv(?array $env = null): self
    {
        [$credentials, $config] = ConfigFactory::fromEnv($env);

        return new self($credentials, $config);
    }

    /**
     * Bootstrap from tradernet.ini.
     */
    public static function fromIni(string $path): self
    {
        [$credentials, $config] = ConfigFactory::fromIni($path);

        return new self($credentials, $config);
    }

    /**
     * Default SID storage for the configured auth mode.
     */
    private function createDefaultStorage(): SessionStorageInterface
    {
        if ($this->config->authMode === AuthMode::KEYS_ONLY) {
            return new InMemorySessionStorage();
        }

        $path = $this->config->sessionPath;
        if ($path === null || $path === '') {
            $home = $_SERVER['HOME'] ?? getenv('HOME');
            if (!is_string($home) || $home === '') {
                throw new ConfigurationException(
                    'Session path is not configured and HOME is unset. '
                    . 'Set ClientConfig::$sessionPath or TRADERNET_SESSION_PATH '
                    . '(refusing to store SID under /tmp).',
                );
            }
            $path = rtrim($home, '/') . '/.tradernet/sessions';
        }

        return new FileSessionStorage($path);
    }
}
