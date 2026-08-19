<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Transport\HttpMethod;

/**
 * Tariffs.
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class TariffApi extends AbstractResource
{
    /**
     * Available tariffs.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/get-list-tariff
     */
    public function list(): array
    {
        return $this->call('GetListTariffs', [], HttpMethod::POST);
    }

    /**
     * {@inheritdoc}
     *
     * Uses API-key identity (same as orders), not SID.
     */
    protected function requiresSid(): bool
    {
        return false;
    }
}
