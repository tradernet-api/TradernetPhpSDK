# Tradernet PHP SDK

[![PHP](https://img.shields.io/badge/PHP-%5E8.3-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Packagist](https://img.shields.io/badge/Packagist-tradernet%2Fsdk-orange?logo=packagist&logoColor=white)](https://packagist.org/packages/tradernet/sdk)

PHP client for the [Tradernet public API V3](https://tradernet.com/tradernet-api/).

Typed, session-aware HTTP commands and optional Amp WebSocket streams for server-side integrations.

| | |
|---|---|
| **Package** | `tradernet/sdk` |
| **Namespace** | `Tradernet\Sdk` |
| **PHP** | `^8.3` |
| **HTTP** | Guzzle `^7.8` |
| **Realtime** | optional `amphp/websocket-client` |

## Features

- Signed requests to `/api/{command}` (HMAC-SHA256 API key pair)
- Optional SID sessions via login/password with local persistence (~2 weeks)
- Circuit breaker against re-authentication storms
- Resource clients mapped to public API sections
- Escape hatch for any V3 command: `$tn->request()`
- WebSocket quotes / account channels via Amp

## Requirements

- PHP 8.3+
- Extensions: `json`, `openssl`
- Composer 2

## Installation

```bash
composer require tradernet/sdk
```

For realtime streams:

```bash
composer require amphp/websocket-client
```

Full guide: [Installing with Composer](docs/installation/using-composer.md).

## Quick start

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Tradernet\Sdk\Tradernet;

$tn = Tradernet::fromEnv();

$quotes = $tn->quotes()->get(['AAPL.US']);
$portfolio = $tn->portfolio()->get();

// WARNING: orders()->buy() places a real trade. See examples/orders.php.
```

### Credentials

**Environment variables** (recommended for containers / CI):

```bash
export TRADERNET_API_KEY='your_public_key'
export TRADERNET_API_SECRET='your_private_key'
export TRADERNET_LOGIN='user@example.com'      # optional, for SID
export TRADERNET_PASSWORD='secret'             # optional, for SID
export TRADERNET_DOMAIN='https://tradernet.com'
```

```php
$tn = Tradernet::fromEnv();
```

Details: [Using environment variables](docs/installation/using-env.md).

**INI file** (Python SDK parity):

```ini
[auth]
public   = YOUR_PUBLIC_KEY
private  = YOUR_PRIVATE_KEY
login    = user@example.com
password = secret
domain   = https://tradernet.com
```

```php
$tn = Tradernet::fromIni(__DIR__ . '/tradernet.ini');
```

Details: [Using tradernet.ini](docs/installation/using-ini.md).

> The SDK **reads** the process environment. It does **not** load `.env` files. Use `vlucas/phpdotenv` (or similar) in your app if needed. Bundled `examples/` load `.env` for convenience only.

## Auth modes

| Mode | Behavior |
|---|---|
| `keys_only` | HMAC only — SID is never obtained |
| `sid_lazy` | SID only when a command requires it (**default**) |
| `sid_eager` | SID obtained at client construction (still attached only when needed) |

Set via `TRADERNET_AUTH_MODE` or `ClientConfig`.

API keys are **per cabinet / domain**. A key from `tradernet.com` will not work on `tradernet.kz` or `freedom24.com` — match `TRADERNET_DOMAIN` to the cabinet that issued the key.

More: [Authentication](docs/authentication.md).

## Resources

```php
$tn->auth();
$tn->user();
$tn->quotes();
$tn->orders();
$tn->portfolio();
$tn->alerts();
$tn->stockLists();
$tn->securitySessions();
$tn->currency();
$tn->reference();
$tn->cps();
$tn->reports();
$tn->news();
$tn->shop();
$tn->tariff();
$tn->websocket();
```

Any command:

```php
$tn->request('getStockQuotesJson', ['tickers' => 'AAPL.US']);
```

See [API resources](docs/resources.md) and [HTTP API](docs/http-api.md).

## WebSocket

Streams authenticate with **SID only** (not API keys). Without SID you get demo quotes.

```php
use Tradernet\Sdk\Ws\Amp\AmpWebSocketClient;

$ws = $tn->websocket(requireSid: true);
$ws->connect();

if ($ws instanceof AmpWebSocketClient) {
    $ws->quotes(['AAPL.US']);
}

foreach ($ws->frames() as $frame) {
    // $frame->event, $frame->data
}
```

```bash
php examples/websocket_quotes.php
TRADERNET_WS_SID=1 php examples/websocket_quotes.php
```

Guide: [WebSocket](docs/websocket.md).

## Examples

```bash
cp .env.example .env   # fill in your keys
composer install
php examples/quotes.php
php examples/portfolio.php
php examples/orders.php
php examples/diagnose.php
```

See the [examples](examples) directory.

## Documentation

| Topic | Link |
|---|---|
| Docs home | [docs/index.md](docs/index.md) |
| Installation | [Composer](docs/installation/using-composer.md) · [Env](docs/installation/using-env.md) · [INI](docs/installation/using-ini.md) |
| Authentication | [docs/authentication.md](docs/authentication.md) |
| HTTP API | [docs/http-api.md](docs/http-api.md) |
| Resources | [docs/resources.md](docs/resources.md) |
| WebSocket | [docs/websocket.md](docs/websocket.md) |
| Security | [docs/security.md](docs/security.md) |
| Development | [docs/development.md](docs/development.md) |
| Upstream API | [tradernet.com/tradernet-api](https://tradernet.com/tradernet-api/) |

## Development

```bash
composer install
composer test
composer stan
composer cs
```

## Security

- Never commit `tradernet.ini`, `.env`, or SID files under `~/.tradernet/`
- Prefer a password `Closure` (Vault / KMS) instead of a plain string
- Treat SID as a bearer token — do not log or print it
- Auth endpoints are rate-limited; do not bypass `ReauthGuard`

Details: [Security](docs/security.md).

## License

[MIT](LICENSE) © Tradernet API Support \<tradernet.com\>
