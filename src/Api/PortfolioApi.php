<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Transport\HttpMethod;

/**
 * Portfolio positions.
 *
 * @see https://tradernet.com/tradernet-api/portfolio-get-changes
 */
final class PortfolioApi extends AbstractResource
{
    /**
     * Cash flows.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/get-cashflows
     */
    public function cashflows(array $params = []): array
    {
        return $this->call('getUserCashFlows', $params, HttpMethod::POST);
    }

    /**
     * Current positions / portfolio JSON.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->call('getPositionJson', [], HttpMethod::POST);
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresSid(): bool
    {
        return false;
    }
}
