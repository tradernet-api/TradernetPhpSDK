# Using environment variables

Configure the SDK from process environment variables. This is the simplest path for containers, CI, and twelve-factor apps.

## Installation

- [Using Composer](using-composer.md)
- [Using environment variables](using-env.md) (this page)
- [Using tradernet.ini](using-ini.md)

---

## Using environment variables

`Tradernet::fromEnv()` (and `ConfigFactory::fromEnv()` / `Credentials` built from env) read `getenv()` / `$_ENV`. There is no `Credentials::fromEnv()` helper. The SDK never parses `.env` files — load them in your application if needed (`vlucas/phpdotenv` is a convenient option).

### 01

#### Set the required keys

Every signed request needs a public/private API key pair.

Terminal

```bash
export TRADERNET_API_KEY='your_public_key'
export TRADERNET_API_SECRET='your_private_key'
```

### 02

#### Add login credentials when you need a SID

SID sessions are required for portfolio identity, WebSocket account channels, and any command that declares `requiresSid()`.

Terminal

```bash
export TRADERNET_LOGIN='user@example.com'
export TRADERNET_PASSWORD='secret'
```

### 03

#### Tune optional settings

| Variable | Required | Default | Description |
|---|---|---|---|
| `TRADERNET_API_KEY` | yes | — | Public API key |
| `TRADERNET_API_SECRET` | yes | — | Private API key |
| `TRADERNET_LOGIN` | for SID | — | Account login |
| `TRADERNET_PASSWORD` | for SID | — | Account password |
| `TRADERNET_DOMAIN` | no | `https://tradernet.com` | API origin (HTTPS allowlist) |
| `TRADERNET_AUTH_MODE` | no | `sid_lazy` | `keys_only` \| `sid_lazy` \| `sid_eager` |
| `TRADERNET_SESSION_PATH` | no | `~/.tradernet/sessions` | SID storage directory |
| `TRADERNET_LANG` | no | `en` | Language cookie |
| `TRADERNET_SID_COOKIE` | no | `SID` | `SID` or `SIDBETA` |

### 04

#### Build the client

`app.php`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Tradernet\Sdk\Tradernet;

$tn = Tradernet::fromEnv();
```

### 05

#### (Optional) Load a `.env` file in examples

Bundled examples call `examples/bootstrap.php`, which loads `.env` via `vlucas/phpdotenv` when present.

Terminal

```bash
cp .env.example .env
# fill in your keys
php examples/quotes.php
```

Continue with [Authentication](../authentication.md).
