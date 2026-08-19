<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Transport\HttpMethod;

/**
 * Trading security sessions.
 *
 * @see https://tradernet.com/tradernet-api/open-security-session
 */
final class SecuritySessionApi extends AbstractResource
{
    /**
     * Security sessions list.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/security-get-list
     */
    public function list(): array
    {
        return $this->call('getSecuritySessions', [], HttpMethod::POST);
    }

    /**
     * Confirm and open security session.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function open(array $params): array
    {
        return $this->call('openSecuritySession', $params, HttpMethod::POST);
    }

    /**
     * Request SMS / code to open security session.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function requestCode(array $params = []): array
    {
        return $this->call('getSecuritySessionCode', $params, HttpMethod::POST);
    }

    /**
     * {@inheritdoc}
     *
     * Uses API-key identity so the session matches {@see OrdersApi} (HMAC),
     * not a SID cookie identity.
     */
    protected function requiresSid(): bool
    {
        return false;
    }
}
