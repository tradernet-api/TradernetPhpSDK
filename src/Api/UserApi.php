<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Transport\HttpMethod;

/**
 * User / OPQ bootstrap data.
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class UserApi extends AbstractResource
{
    /**
     * Anketa / KYC info.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/
     */
    public function anketaInfo(array $params = []): array
    {
        return $this->call('getAnketa', $params, HttpMethod::POST, true);
    }

    /**
     * User info.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/get-user-info
     */
    public function info(): array
    {
        return $this->call('getUserInfo', [], HttpMethod::POST, true);
    }

    /**
     * Initial user payload (OPQ).
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/auth-get-opq
     */
    public function opq(): array
    {
        return $this->call('getOPQ', [], HttpMethod::POST, true);
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresSid(): bool
    {
        return true;
    }
}
