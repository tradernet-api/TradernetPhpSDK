# Authentication

Tradernet V3 accepts **HMAC API keys** on every HTTP call and an optional **SID** cookie for account identity. The SDK manages both.

## Overview

Server-side auth is effectively `tryBySid() ?: tryByApiKey()`. If a SID cookie is present, it wins — even when you meant to act as the API-key owner. That is why the SDK attaches SID **only** when a resource/command declares that it needs one.

## Auth modes

| Mode | Behavior |
|---|---|
| `keys_only` | HMAC only. SID is never obtained. |
| `sid_lazy` | SID is obtained only for commands that require it. **Default.** |
| `sid_eager` | SID is obtained at client construction (still attached only when needed). |

Set via `TRADERNET_AUTH_MODE` or `ClientConfig::$authMode`.

## SID lifecycle

### 01

#### Obtain a session

When a SID is required and none is stored, the SDK calls `authByLogin` with `rememberMe=1` (~2 weeks).

### 02

#### Persist locally

The session is written under `sha256(domain|configuredLogin)` (file mode `0600`, default directory `~/.tradernet/sessions`). SMS / explicit logins also land on that canonical key when `TRADERNET_LOGIN` is set, so a process restart still finds the SID.

### 03

#### Re-auth on a dead SID

For SID-required **read** commands, a dead SID triggers invalidate → one re-login → a single retry.

Trade / write commands (`putTradeOrder`, …) are **not** auto-retried.

### 04

#### Circuit breaker

`ReauthGuard` stops login storms: max 3 attempts / 15 minutes (persistent), nested re-auth forbidden, permanent errors (bad password, 2FA) open the circuit immediately.

`authByLogin` is always sent **without** a SID cookie so login cannot loop on itself. Security-session errors are not treated as a dead SID.

## Password login and SMS / 2FA

`AuthApi` goes through `SessionManager`:

```php
// Password login — adopts SID via SessionManager + ReauthGuard
$tn->auth()->byLogin('user@example.com', 'secret');

// SMS / 2FA — both arguments are required
$tn->auth()->bySms($authCodeId, $smsCode);

// Optional: force storage label (defaults to configured login, then authCodeId)
$tn->auth()->bySms($authCodeId, $smsCode, $tn->sessions()->configuredLogin());
```

Helpers on `SessionManager` (`$tn->sessions()`):

- `loginWithPassword(string $login, string $password, int $rememberMe = 1): array`
- `adopt(Session $session): void`
- `adoptFromResponse(array $response, string $login): Session`

## Domains

API keys live in the cabinet database of a specific domain. Allowed HTTPS origins are validated by `Domain` (for example `tradernet.com`, `tradernet.global`, `tradernet.kz`, `freedom24.com`).

A key minted on one domain will fail with `Invalid api key provided` on another.

Continue with [HTTP API](http-api.md).
