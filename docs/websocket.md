# WebSocket

Realtime streams over `wss://wss.{domain}/`. Authentication is **SID only** — API keys are not used on the socket.

## Overview

Without a SID the server sends demo quotes and refuses account channels (orders, sessions). With `requireSid: true` the SDK appends `?SID=...` after ensuring a session.

| Mode | URL | Data |
|---|---|---|
| `requireSid: false` | `wss://wss.{domain}/` | Demo quotes / order book |
| `requireSid: true` | `wss://wss.{domain}/?SID=...` | Real account data |

## Installation

### 01

#### Install Amp WebSocket client

Terminal

```bash
composer require amphp/websocket-client
```

### 02

#### Provide login credentials

Real streams need `TRADERNET_LOGIN` and `TRADERNET_PASSWORD` so the SDK can obtain a SID.

### 03

#### Connect and subscribe

`stream.php`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Tradernet\Sdk\Tradernet;
use Tradernet\Sdk\Ws\Amp\AmpWebSocketClient;

$tn = Tradernet::fromEnv();
$ws = $tn->websocket(requireSid: true);
$ws->connect();

if ($ws instanceof AmpWebSocketClient) {
    $ws->quotes(['AAPL.US']);
}

foreach ($ws->frames() as $frame) {
    // $frame->event, $frame->data
}
```

### 04

#### Run the bundled example

Terminal

```bash
composer require amphp/websocket-client
php examples/websocket_quotes.php                     # demo data
TRADERNET_WS_SID=1 php examples/websocket_quotes.php  # real account data
```

## Cloudflare User-Agent

Cloudflare fronts `wss.{domain}` and rejects well-known HTTP library agents. The SDK sends `ClientConfig::$userAgent` (default `tradernet-php-sdk/0.1`) on the handshake.

Overriding it with something like `amphp/http-client` turns the handshake into HTTP 403.

## Reconnect behavior

- Clean server close reconnects with backoff up to `maxReconnects`.
- After a few healthy frames the reconnect counter resets (long-lived streams survive intermittent drops).
- Demo payloads while `requireSid: true` invalidate the SID, re-login, and reconnect (also capped).
- Permanent auth / configuration errors are not swallowed by reconnect.
- `close()` stops further reconnect attempts.
- There is no busy-spin when `receive()` returns `null`.

Continue with [Security](security.md).
