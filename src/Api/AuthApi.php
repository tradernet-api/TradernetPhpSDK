<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Auth\Session;
use Tradernet\Sdk\Exception\AuthenticationException;
use Tradernet\Sdk\Exception\ConfigurationException;
use Tradernet\Sdk\Exception\ReauthBlockedException;
use Tradernet\Sdk\Transport\HttpMethod;

/**
 * Authorization commands.
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class AuthApi extends AbstractResource
{
    /**
     * Login by email/password and persist the SID via SessionManager.
     *
     * Goes through ReauthGuard (same circuit breaker as internal authenticate()).
     *
     * @param int $rememberMe 1 = remember ~2 weeks
     *
     * @throws AuthenticationException
     * @throws ConfigurationException
     * @throws ReauthBlockedException
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/auth-login
     */
    public function byLogin(string $login, string $password, int $rememberMe = 1): array
    {
        return $this->sessions->loginWithPassword($login, $password, $rememberMe);
    }

    /**
     * Login by SMS code and persist the resulting SID.
     *
     * @param null|string $login Storage label (defaults to configured login or authCodeId)
     *
     * @throws AuthenticationException
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/auth-by-sms
     */
    public function bySms(string $authCodeId, string $sms, ?string $login = null): array
    {
        $response = $this->call(
            'authBySms',
            [
                'authCodeId' => $authCodeId,
                'sms' => $sms,
            ],
            HttpMethod::POST,
            false,
        );

        $label = $login
            ?? $this->sessions->configuredLogin()
            ?? $authCodeId;

        $this->sessions->adoptFromResponse($response, $label);

        return $response;
    }

    /**
     * Ensures a SID session exists (login if needed).
     *
     * @see https://tradernet.com/tradernet-api/auth-login
     */
    public function ensureSession(): ?Session
    {
        return $this->sessions->ensureSession(true);
    }

    /**
     * Request SMS code for phone login.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/get-auth-sms
     */
    public function getSms(string $phone): array
    {
        return $this->call('getAuthSms', ['phone' => $phone], HttpMethod::POST, false);
    }

    /**
     * Auth info for current session.
     *
     * @return array<string, mixed>
     */
    public function info(): array
    {
        return $this->call('getAuthInfo', [], HttpMethod::POST, true);
    }

    /**
     * Invalidates stored SID.
     */
    public function logoutLocal(): void
    {
        $this->sessions->invalidate();
    }

    /**
     * Current SID info.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/auth-get-sidinfo
     */
    public function sidInfo(): array
    {
        return $this->call('getSidInfo', [], HttpMethod::POST, true);
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresSid(): bool
    {
        return false;
    }
}
