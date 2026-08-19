<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Ws\Amp;

use Amp\Websocket\Client\WebsocketHandshake;
use Generator;
use Throwable;
use Tradernet\Sdk\Auth\SessionManager;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Exception\AuthenticationException;
use Tradernet\Sdk\Exception\ConfigurationException;
use Tradernet\Sdk\Exception\ReauthBlockedException;
use Tradernet\Sdk\Ws\Channel;
use Tradernet\Sdk\Ws\Frame;
use Tradernet\Sdk\Ws\WebSocketClientInterface;
use Traversable;

use function Amp\delay;
use function Amp\Websocket\Client\connect;

/**
 * Amp-based WebSocket client.
 *
 * Requires: composer require amphp/websocket-client
 *
 * @see https://tradernet.com/tradernet-api/websocket
 */
final class AmpWebSocketClient implements WebSocketClientInterface
{
    private bool $closedByCaller = false;

    private mixed $connection = null;

    private int $reconnectAttempt = 0;

    private int $stableFrames = 0;

    /** @var list<array{0: Channel, 1: mixed}> */
    private array $subscriptions = [];

    /**
     * @param bool $requireSid Attach SID for real account streams
     * @param int $maxReconnects Cap on connection drops / demo recoveries
     */
    public function __construct(
        private readonly ClientConfig $config,
        private readonly SessionManager $sessions,
        private readonly bool $requireSid = true,
        private readonly int $maxReconnects = 10,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        $this->closedByCaller = true;
        if (
            is_object($this->connection)
            && method_exists($this->connection, 'close')
        ) {
            $this->connection->close();
        }
        $this->connection = null;
    }

    /**
     * {@inheritdoc}
     */
    public function connect(): void
    {
        $this->assertAmpAvailable();
        $this->closedByCaller = false;
        $this->connection = $this->openConnection();
        $this->reconnectAttempt = 0;
        $this->stableFrames = 0;
        $this->resubscribe();
    }

    /**
     * {@inheritdoc}
     *
     * @return Generator<int, Frame>
     */
    public function frames(): Traversable
    {
        $this->assertAmpAvailable();

        if ($this->connection === null) {
            $this->connect();
        }

        while (!$this->closedByCaller) {
            try {
                foreach ($this->receiveLoop() as $frame) {
                    ++$this->stableFrames;
                    // Treat a short burst of healthy frames as a recovered connection.
                    if ($this->stableFrames >= 3) {
                        $this->reconnectAttempt = 0;
                    }

                    if (
                        $frame->event === 'userData'
                        && $this->requireSid
                        && $this->looksLikeDemo($frame->data)
                    ) {
                        if ($this->reconnectAttempt >= $this->maxReconnects) {
                            throw new ConfigurationException(
                                'WebSocket stayed in demo mode after max reconnects',
                            );
                        }
                        $this->sessions->invalidate();
                        $this->sessions->ensureSession(true);
                        $this->reconnect();

                        continue 2;
                    }

                    yield $frame;
                }

                if ($this->closedByCaller || $this->connection === null) {
                    return;
                }

                if ($this->reconnectAttempt >= $this->maxReconnects) {
                    throw new ConfigurationException(
                        'WebSocket closed after max reconnects',
                    );
                }
                $this->reconnect();
            } catch (AuthenticationException|ConfigurationException|ReauthBlockedException $e) {
                throw $e;
            } catch (Throwable $e) {
                if ($this->closedByCaller) {
                    return;
                }
                if ($this->reconnectAttempt >= $this->maxReconnects) {
                    throw $e;
                }
                $this->reconnect();
            }
        }
    }

    /**
     * Subscribe to market status updates.
     */
    public function markets(): void
    {
        $this->subscribe(Channel::MARKETS);
    }

    /**
     * Subscribe to order book updates.
     *
     * @param list<string> $tickers
     */
    public function orderbook(array $tickers): void
    {
        $this->subscribe(Channel::ORDERBOOK, $tickers);
    }

    /**
     * Subscribe to order updates.
     */
    public function orders(): void
    {
        $this->subscribe(Channel::ORDERS);
    }

    /**
     * Subscribe to portfolio updates.
     */
    public function portfolio(): void
    {
        $this->subscribe(Channel::PORTFOLIO);
    }

    /**
     * Subscribe to quote updates.
     *
     * @param list<string> $tickers
     */
    public function quotes(array $tickers): void
    {
        $this->subscribe(Channel::QUOTES, $tickers);
    }

    /**
     * Subscribe to security session events.
     */
    public function sessionsChannel(): void
    {
        $this->subscribe(Channel::SESSIONS);
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(Channel $channel, mixed $params = null): void
    {
        foreach ($this->subscriptions as [$existing, $existingParams]) {
            if ($existing === $channel && $existingParams === $params) {
                return;
            }
        }

        $this->subscriptions[] = [$channel, $params];
        if ($this->connection !== null) {
            $this->sendSubscribe($channel, $params);
        }
    }

    /**
     * Ensures amphp/websocket-client is installed.
     *
     * @throws ConfigurationException
     */
    private function assertAmpAvailable(): void
    {
        if (!function_exists('Amp\Websocket\Client\connect')) {
            throw new ConfigurationException(
                'amphp/websocket-client is required for AmpWebSocketClient. '
                . 'Run: composer require amphp/websocket-client',
            );
        }
    }

    /**
     * Builds a handshake carrying the SDK User-Agent.
     *
     * Cloudflare answers 403 to the default amphp/http-client agent, so the
     * plain-string form of connect() cannot be used here.
     *
     * @throws ConfigurationException
     */
    private function createHandshake(string $url): WebsocketHandshake
    {
        if (!class_exists(WebsocketHandshake::class)) {
            throw new ConfigurationException(
                'Amp\Websocket\Client\WebsocketHandshake is missing; '
                . 'install amphp/websocket-client ^2.0',
            );
        }

        return (new WebsocketHandshake($url))
            ->withHeader('User-Agent', $this->config->userAgent)
        ;
    }

    private function looksLikeDemo(mixed $data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        if (array_key_exists('isDemo', $data)) {
            return (bool) $data['isDemo'];
        }

        return ($data['mode'] ?? null) === 'demo';
    }

    /**
     * Opens a WebSocket connection, attaching SID when required.
     *
     * SID is passed as a query parameter because the public WS protocol
     * authenticates that way; avoid logging the resulting URL.
     */
    private function openConnection(): mixed
    {
        $url = $this->config->websocketUrl();
        if ($this->requireSid) {
            $session = $this->sessions->ensureSession(true);
            if ($session !== null) {
                $url .= '?SID=' . rawurlencode($session->sid);
            }
        }

        $this->assertAmpAvailable();

        return connect($this->createHandshake($url));
    }

    /**
     * @return Generator<int, Frame>
     *
     * @phpstan-impure
     */
    private function receiveLoop(): Generator
    {
        if (!is_object($this->connection)) {
            return;
        }

        if (!method_exists($this->connection, 'receive')) {
            throw new ConfigurationException('Unsupported Amp WebSocket connection type');
        }

        while ($message = $this->connection->receive()) {
            if (is_object($message) && method_exists($message, 'buffer')) {
                $buffered = $message->buffer();
                $text = is_string($buffered) ? $buffered : '';
            } elseif (is_string($message)) {
                $text = $message;
            } else {
                continue;
            }
            /** @var mixed $decoded */
            $decoded = json_decode($text, true);
            if (!is_array($decoded)) {
                continue;
            }
            /** @var array<int, mixed> $decoded */
            yield Frame::fromArray($decoded);
        }
    }

    /**
     * Reconnects with exponential backoff and restores subscriptions.
     *
     * @throws ConfigurationException
     */
    private function reconnect(): void
    {
        if ($this->closedByCaller) {
            return;
        }

        ++$this->reconnectAttempt;
        $this->stableFrames = 0;
        $delay = min(30, 2 ** min($this->reconnectAttempt, 4)) + random_int(0, 1);
        if (function_exists('Amp\delay')) {
            delay($delay);
        } else {
            usleep($delay * 1_000_000);
        }
        if (
            is_object($this->connection)
            && method_exists($this->connection, 'close')
        ) {
            $this->connection->close();
        }
        $this->connection = null;
        $this->connection = $this->openConnection();
        $this->resubscribe();
    }

    /**
     * Re-sends all active channel subscriptions.
     */
    private function resubscribe(): void
    {
        foreach ($this->subscriptions as [$channel, $params]) {
            $this->sendSubscribe($channel, $params);
        }
    }

    /**
     * Sends a subscribe frame for one channel.
     */
    private function sendSubscribe(Channel $channel, mixed $params): void
    {
        if (!is_object($this->connection)) {
            return;
        }

        $message = $params === null
            ? [$channel->value]
            : [$channel->value, $params];

        $payload = json_encode($message, JSON_THROW_ON_ERROR);
        if (method_exists($this->connection, 'sendText')) {
            $this->connection->sendText($payload);
        } elseif (method_exists($this->connection, 'send')) {
            $this->connection->send($payload);
        }
    }
}
