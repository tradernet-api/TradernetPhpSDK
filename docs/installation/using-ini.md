# Using tradernet.ini

Configure the SDK from an INI file for parity with the Python Tradernet SDK.

## Installation

- [Using Composer](using-composer.md)
- [Using environment variables](using-env.md)
- [Using tradernet.ini](using-ini.md) (this page)

---

## Using tradernet.ini

`Tradernet::fromIni()` reads a local INI file through `ConfigFactory::fromIni()`.

### 01

#### Create the file

Copy the example and fill in your cabinet credentials.

Terminal

```bash
cp tradernet.ini.example tradernet.ini
```

### 02

#### Fill the `[auth]` section

`tradernet.ini`

```ini
[auth]
public   = YOUR_PUBLIC_KEY
private  = YOUR_PRIVATE_KEY
login    = user@example.com
password = secret
domain   = https://tradernet.com
```

Optional keys supported by the factory: `auth_mode`, `lang`, `session_path`, `sid_cookie`.

### 03

#### Keep secrets out of git

`tradernet.ini` and `.env` are listed in `.gitignore`. Never commit them.

### 04

#### Create a client from the file

`app.php`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Tradernet\Sdk\Tradernet;

$tn = Tradernet::fromIni(__DIR__ . '/tradernet.ini');

$quotes = $tn->quotes()->get(['AAPL.US']);
```

### 05

#### Prefer a password Closure in production

When building `Credentials` manually, pass the password as a `Closure` so secrets stay out of memory dumps until needed (Vault / KMS).

```php
use Tradernet\Sdk\Config\Credentials;

$credentials = new Credentials(
    apiKey: $public,
    apiSecret: $private,
    login: $login,
    password: static fn (): string => $vault->read('tradernet/password'),
);
```

Continue with [Authentication](../authentication.md).
