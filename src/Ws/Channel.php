<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Ws;

/**
 * WebSocket channel / subscribe event names.
 */
enum Channel: string
{
    case MARKETS = 'markets';
    case ORDERBOOK = 'orderbook';
    case ORDERS = 'orders';
    case PORTFOLIO = 'portfolio';
    case QUOTES = 'quotes';
    case SESSIONS = 'sessions';

    /**
     * Server event name in incoming frames.
     */
    public function eventName(): string
    {
        return match ($this) {
            self::QUOTES => 'q',
            self::ORDERBOOK => 'b',
            self::PORTFOLIO => 'portfolio',
            self::ORDERS => 'orders',
            self::MARKETS => 'markets',
            self::SESSIONS => 'sessions',
        };
    }
}
