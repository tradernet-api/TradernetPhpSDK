<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Auth;

use DateTimeImmutable;
use Throwable;
use Tradernet\Sdk\Clock\ClockInterface;
use Tradernet\Sdk\Config\AuthMode;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Config\Credentials;
use Tradernet\Sdk\Exception\AuthenticationException;
use Tradernet\Sdk\Exception\ConfigurationException;
use Tradernet\Sdk\Exception\ReauthBlockedException;
use Tradernet\Sdk\Support\Cast;
use Tradernet\Sdk\Transport\HttpMethod;
use Tradernet\Sdk\Transport\TransportInterface;

/**
 * Obtains, validates and persists SID sessions.
 */
final class SessionManager
{
    private ?Session $current = null;

    public function __construct(
        private readonly Credentials $credentials,
        private readonly ClientConfig $config,
        private readonly TransportInterface $transport,
        private readonly SessionStorageInterface $storage,
        private readonly ReauthGuard $reauthGuard,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Persists an externally obtained SID (e.g. SMS/2FA login).
     *
     * Always writes under {@see storageKey()} when credentials have a login, so
     * {@see loadValidSession()} finds the SID after process restart.
     */
    public function adopt(Session $session): void
    {
        $this->current = $session;
        $this->storage->save($this->storageKeyFor($session), $session);
    }

    /**
     * Builds and persists a session from an authByLogin / authBySms response.
     *
     * @param array<string, mixed> $response
     * @param string $login Login or phone label for storage key
     *
     * @throws AuthenticationException
     */
    public function adoptFromResponse(array $response, string $login): Session
    {
        $sid = $this->extractSid($response);
        if ($sid === null || $sid === '') {
            throw new AuthenticationException(
                'Auth response did not return SID',
                AuthenticationException::CODE_PERMANENT,
            );
        }

        $userId = isset($response['userId']) ? Cast::int($response['userId']) : null;
        $nowTs = $this->clock->now();
        $session = new Session(
            sid: $sid,
            sidName: $this->config->sidCookieName,
            userId: $userId,
            login: $login,
            createdAt: new DateTimeImmutable('@' . $nowTs),
            expiresAt: new DateTimeImmutable('@' . ($nowTs + $this->config->sidTtlSeconds)),
            domain: $this->config->domain,
        );

        $this->adopt($session);

        return $session;
    }

    /**
     * Forces a fresh login (still subject to ReauthGuard).
     *
     * @throws AuthenticationException
     * @throws ConfigurationException
     * @throws ReauthBlockedException
     */
    public function authenticate(): Session
    {
        if (!$this->credentials->hasLoginPassword()) {
            throw new ConfigurationException(
                'Login and password are required to obtain SID',
            );
        }

        $key = $this->storageKey();
        $lock = $this->storage->lock($key);

        try {
            return $this->authenticateLocked($key);
        } finally {
            $lock->unlock();
        }
    }

    /**
     * Whether login/password are available for a fresh authByLogin.
     */
    public function canReauthenticate(): bool
    {
        return $this->credentials->hasLoginPassword()
            && $this->config->authMode !== AuthMode::KEYS_ONLY;
    }

    /**
     * Login label from configured credentials, if any.
     */
    public function configuredLogin(): ?string
    {
        $login = $this->credentials->login();

        return $login !== null && $login !== '' ? $login : null;
    }

    /**
     * Current in-memory session if any.
     */
    public function current(): ?Session
    {
        return $this->current ?? $this->loadValidSession();
    }

    /**
     * Returns current session, obtaining one when required by auth mode.
     *
     * @param bool $required Whether SID is mandatory for the caller
     *
     * @throws AuthenticationException
     * @throws ConfigurationException
     * @throws ReauthBlockedException
     */
    public function ensureSession(bool $required = true): ?Session
    {
        if ($this->config->authMode === AuthMode::KEYS_ONLY) {
            if ($required) {
                throw new ConfigurationException(
                    'SID is required but auth mode is keys_only',
                );
            }

            return null;
        }

        if (!$required && $this->config->authMode === AuthMode::SID_LAZY) {
            $session = $this->loadValidSession();

            return $session;
        }

        $session = $this->loadValidSession();
        if ($session !== null) {
            return $session;
        }

        return $this->authenticate();
    }

    /**
     * Invalidates current SID and storage entry.
     */
    public function invalidate(): void
    {
        if ($this->current !== null) {
            $this->storage->delete(
                hash('sha256', $this->current->domain . '|' . $this->current->login),
            );
            $this->storage->delete($this->storageKeyFor($this->current));
        }

        $this->storage->delete($this->storageKey());
        $this->current = null;
    }

    /**
     * Login with explicit credentials, subject to ReauthGuard, and persist SID.
     *
     * @throws AuthenticationException
     * @throws ConfigurationException
     * @throws ReauthBlockedException
     *
     * @return array<string, mixed> Raw authByLogin payload
     */
    public function loginWithPassword(
        string $login,
        string $password,
        int $rememberMe = 1,
    ): array {
        if ($login === '' || $password === '') {
            throw new ConfigurationException('Login and password are required');
        }

        $key = $this->lockKeyForLogin($login);
        $lock = $this->storage->lock($key);

        try {
            return $this->performPasswordLogin($key, $login, $password, $rememberMe);
        } finally {
            $lock->unlock();
        }
    }

    /**
     * Storage key for configured login+domain (canonical SID lookup key).
     */
    public function storageKey(): string
    {
        $login = $this->credentials->login() ?? '';

        return hash('sha256', $this->config->domain . '|' . $login);
    }

    /**
     * Login flow executed while the storage lock is held.
     *
     * @throws AuthenticationException
     * @throws ReauthBlockedException
     */
    private function authenticateLocked(string $key): Session
    {
        $existing = $this->loadValidSession();
        if ($existing !== null) {
            return $existing;
        }

        $this->reauthGuard->allow($key);
        $this->reauthGuard->begin();

        try {
            $this->reauthGuard->recordAttempt($key);
            $session = $this->loginAndPersist($key);
            $this->reauthGuard->reset($key);
            $this->current = $session;

            return $session;
        } catch (AuthenticationException $e) {
            if ($e->isPermanent()) {
                $this->reauthGuard->recordAttempt($key, openImmediately: true);
            }

            throw $e;
        } finally {
            $this->reauthGuard->end();
        }
    }

    /**
     * Extracts SID from authByLogin response fields.
     *
     * @param array<string, mixed> $response
     */
    private function extractSid(array $response): ?string
    {
        foreach (['SID', 'sid', 'sessionId'] as $field) {
            if (isset($response[$field]) && is_scalar($response[$field])) {
                return (string) $response[$field];
            }
        }

        return null;
    }

    /**
     * Returns in-memory or persisted session when still valid.
     */
    private function loadValidSession(): ?Session
    {
        if ($this->current !== null) {
            $now = new DateTimeImmutable('@' . $this->clock->now());
            if (!$this->current->isExpired($now)) {
                return $this->current;
            }
            $this->current = null;
        }

        $session = $this->storage->load($this->storageKey());
        if ($session === null) {
            return null;
        }

        $now = new DateTimeImmutable('@' . $this->clock->now());
        if ($session->isExpired($now)) {
            $this->storage->delete($this->storageKey());

            return null;
        }

        $this->current = $session;

        return $session;
    }

    /**
     * Lock key for an explicit password login.
     */
    private function lockKeyForLogin(string $login): string
    {
        $configured = $this->credentials->login();
        if ($configured !== null && $configured !== '') {
            return $this->storageKey();
        }

        return hash('sha256', $this->config->domain . '|' . $login);
    }

    /**
     * Calls authByLogin and persists the resulting session.
     *
     * @throws AuthenticationException
     */
    private function loginAndPersist(string $key): Session
    {
        $login = (string) $this->credentials->login();
        $password = $this->credentials->password();

        try {
            $response = $this->transport->request(
                'authByLogin',
                [
                    'login' => $login,
                    'password' => $password,
                    'rememberMe' => 1,
                ],
                HttpMethod::POST,
                null,
                false,
            );
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $permanent = $this->looksPermanent($message);

            throw new AuthenticationException(
                'authByLogin failed: ' . $message,
                $permanent ? AuthenticationException::CODE_PERMANENT : AuthenticationException::CODE_TRANSIENT,
                $e,
            );
        }

        $session = $this->adoptFromResponse($response, $login);

        return $session;
    }

    /**
     * Whether an auth error message indicates a permanent failure.
     */
    private function looksPermanent(string $message): bool
    {
        $lower = strtolower($message);

        return preg_match('/\b(invalid|wrong|incorrect)\b.*\bpassword\b/', $lower) === 1
            || preg_match('/\bpassword\b.*\b(invalid|wrong|incorrect)\b/', $lower) === 1
            || str_contains($lower, 'invalid login')
            || str_contains($lower, 'wrong login')
            || preg_match('/\b2fa\b/', $lower) === 1
            || str_contains($lower, 'two-factor')
            || str_contains($lower, 'blocked')
            || str_contains($lower, 'disabled');
    }

    /**
     * Password login under ReauthGuard (caller holds the storage lock).
     *
     * @throws AuthenticationException
     * @throws ReauthBlockedException
     *
     * @return array<string, mixed>
     */
    private function performPasswordLogin(
        string $key,
        string $login,
        string $password,
        int $rememberMe,
    ): array {
        $this->reauthGuard->allow($key);
        $this->reauthGuard->begin();

        try {
            $this->reauthGuard->recordAttempt($key);

            try {
                $response = $this->transport->request(
                    'authByLogin',
                    [
                        'login' => $login,
                        'password' => $password,
                        'rememberMe' => $rememberMe,
                    ],
                    HttpMethod::POST,
                    null,
                    false,
                );
            } catch (Throwable $e) {
                $message = $e->getMessage();
                $permanent = $this->looksPermanent($message);
                if ($permanent) {
                    $this->reauthGuard->recordAttempt($key, openImmediately: true);
                }

                throw new AuthenticationException(
                    'authByLogin failed: ' . $message,
                    $permanent
                        ? AuthenticationException::CODE_PERMANENT
                        : AuthenticationException::CODE_TRANSIENT,
                    $e,
                );
            }

            $this->adoptFromResponse($response, $login);
            $this->reauthGuard->reset($key);
        } finally {
            $this->reauthGuard->end();
        }

        return $response;
    }

    /**
     * Canonical disk key for a session: prefer configured credentials login.
     */
    private function storageKeyFor(Session $session): string
    {
        $configured = $this->credentials->login();
        if ($configured !== null && $configured !== '') {
            return hash('sha256', $this->config->domain . '|' . $configured);
        }

        return hash('sha256', $session->domain . '|' . $session->login);
    }
}
