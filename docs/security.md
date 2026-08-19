# Security

Practical rules for running the SDK in production.

## Secrets

### 01

#### Keep credentials off disk in git

Never commit `tradernet.ini`, `.env`, or session JSON under `~/.tradernet/`.

### 02

#### Prefer a password Closure

Store client passwords only on trusted servers. Pass a `Closure` provider so the secret is resolved lazily (Vault / KMS).

```php
new Credentials(
    apiKey: $public,
    apiSecret: $private,
    login: $login,
    password: static fn (): string => $vault->read('tradernet/password'),
);
```

### 03

#### Treat SID as a bearer token

`Credentials` properties are private; `json_encode` / `var_dump` mask secrets; PHP serialize is refused. Examples never print SID.

WebSocket auth appends `?SID=` to the URL (server protocol). Do not enable debug logging of handshake URLs.

## Identity isolation

Do **not** attach SID to API-key-only commands. The server prefers SID when both are present, which can silently switch identity and break order security sessions.

The SDK attaches SID only when `requiresSid` is true.

## Rate limits

Auth endpoints are rate-limited (~5 req/min). `ReauthGuard` is mandatory for SID flows — do not bypass it with manual login loops.

HTTP `429` surfaces as `RateLimitException` with optional `retryAfterSeconds` from the `Retry-After` header. Idempotent resource calls may retry once when the delay is ≤ 5 seconds; otherwise handle the exception in your app.

## Domain allowlist

`ClientConfig`, `PublicApiClient`, and `AmpTransport` validate the API origin through `Domain`. Only known HTTPS Tradernet / Freedom cabinets are accepted; plain HTTP and arbitrary hosts are rejected. Default Guzzle / Amp clients disable redirects so credentials cannot follow an open redirect off-allowlist.

If you inject a custom Guzzle `Client` into `PublicApiClient`, you must pin `base_uri` yourself and set `allow_redirects` to `false`.

## Session storage and Amp fibers

`FileSessionStorage` uses `flock` and re-entrant locks within one process. Do not share one storage instance across Amp fibers without external serialization — `flock` blocks the OS thread, not a fiber mutex. Prefer one worker / process for SID file I/O.
