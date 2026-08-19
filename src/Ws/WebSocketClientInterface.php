<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Ws;

use Tradernet\Sdk\Ws\Amp\AmpWebSocketClient;
use Traversable;

/**
 * Realtime WebSocket client contract.
 *
 * Amp implementation: {@see AmpWebSocketClient} (requires amphp/websocket-client).
 */
interface WebSocketClientInterface
{
    /**
     * Closes connection.
     */
    public function close(): void;

    /**
     * Opens connection (optionally with SID query param).
     */
    public function connect(): void;

    /**
     * Iterate incoming frames (blocking or fiber-based depending on implementation).
     *
     * @return Traversable<int, Frame>
     */
    public function frames(): Traversable;

    /**
     * Subscribe to a channel.
     *
     * @param mixed $params Channel-specific params (e.g. ticker list)
     */
    public function subscribe(Channel $channel, mixed $params = null): void;
}
