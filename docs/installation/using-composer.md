# Installing with Composer

Installing Tradernet as a Composer package is the recommended way to use the SDK in PHP applications.

## Installation

- [Using Composer](using-composer.md) (this page)
- [Using environment variables](using-env.md)
- [Using tradernet.ini](using-ini.md)

---

## Using Composer

Composer pulls the SDK from Packagist and wires PSR-4 autoloading for the `Tradernet\Sdk\` namespace.

### 01

#### Create or open your project

Start from any PHP ^8.3 project that already uses Composer.

Terminal

```bash
cd my-project
```

### 02

#### Install the SDK

Install `tradernet/sdk` via Composer.

Terminal

```bash
composer require tradernet/sdk
```

### 03

#### (Optional) Install WebSocket support

Realtime streams need Amp. The SDK declares it as a suggestion so HTTP-only apps stay lean.

Terminal

```bash
composer require amphp/websocket-client
```

### 04

#### Provide credentials

Use environment variables or a local INI file. The SDK **reads** the process environment; it does **not** load `.env` files by itself.

`.env` (example)

```dotenv
TRADERNET_API_KEY=your_public_key
TRADERNET_API_SECRET=your_private_key
TRADERNET_LOGIN=user@example.com
TRADERNET_PASSWORD=secret
TRADERNET_DOMAIN=https://tradernet.com
```

See [Using environment variables](using-env.md) and [Using tradernet.ini](using-ini.md).

### 05

#### Create a client and call the API

`app.php`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Tradernet\Sdk\Tradernet;

$tn = Tradernet::fromEnv();

$quotes = $tn->quotes()->get(['AAPL.US']);
print_r($quotes);
```

### 06

#### Start using resources

Resource accessors mirror the public API sections:

```php
$tn->quotes()->get(['AAPL.US']);
$tn->reference()->securities(['AAPL.US']);
// Live order helpers ($tn->orders()->buy / sell) place real trades — see docs/resources.md.
```

Continue with [Authentication](../authentication.md) and [API resources](../resources.md).

---

**Are you stuck?** API keys are stored per cabinet database. A key created on `tradernet.com` will not authenticate against `tradernet.kz` or `freedom24.com`. Match `TRADERNET_DOMAIN` to the cabinet that issued the key.
