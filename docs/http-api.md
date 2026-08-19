# HTTP API

Every V3 command is a signed HTTP request to `/api/{command}`. The SDK signs with HMAC-SHA256 using your public/private key pair.

## Overview

Resource classes wrap documented commands. When you need a command that has no helper yet, use the escape hatch.

### 01

#### Prefer a resource helper

```php
$quotes = $tn->quotes()->get(['AAPL.US']);
```

### 02

#### Or call any command directly

```php
$result = $tn->request('getStockQuotesJson', [
    'tickers' => 'AAPL.US',
]);
```

### 03

#### Control SID attachment

```php
// SID only when the command needs account identity
$tn->request('getPositionJson', [], requiresSid: true);

// HMAC identity only — never attach SID
$tn->request('getStockQuotesJson', ['tickers' => 'AAPL.US'], requiresSid: false);
```

## Signing rules

| Method | Signed payload |
|---|---|
| `POST` | request body + timestamp |
| `GET` | timestamp |

This matches Tradernet `V3AuthenticatorMiddleware`.

## Transports

| Transport | When to use |
|---|---|
| `PublicApiClient` (Guzzle) | Default blocking HTTP |
| `AmpTransport` | Non-blocking HTTP inside an Amp loop (`suggest`: `amphp/http-client`) |
| `FakeTransport` | Unit tests |

```php
use Tradernet\Sdk\Transport\FakeTransport;

$transport = new FakeTransport();
$tn = new Tradernet($credentials, transport: $transport);
```

## Errors

| Exception | Meaning |
|---|---|
| `ApiErrorException` | API returned an error payload / 4xx business error |
| `AuthenticationException` | Login / SID failure |
| `ReauthBlockedException` | Circuit breaker open |
| `RateLimitException` | HTTP 429; see `$retryAfterSeconds` when `Retry-After` was sent |
| `TransportException` | Network / HTTP transport failure |
| `InvalidResponseException` | Response could not be parsed |
| `ConfigurationException` | Missing credentials, bad domain, missing Amp, … |

### Rate limits (429)

```php
use Tradernet\Sdk\Exception\RateLimitException;

try {
    $tn->quotes()->get(['AAPL.US']);
} catch (RateLimitException $e) {
    sleep($e->retryAfterSeconds ?? 1);
}
```

Idempotent helpers may auto-retry once when `Retry-After` is ≤ 5 seconds. Trade / write commands are never auto-retried.

Continue with [API resources](resources.md).
