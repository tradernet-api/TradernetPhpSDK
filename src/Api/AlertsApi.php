<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Transport\HttpMethod;

/**
 * Price alerts.
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class AlertsApi extends AbstractResource
{
    /**
     * Add price alert.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/alerts-add
     */
    public function add(array $params): array
    {
        return $this->call('togglePriceAlert', array_merge($params, ['del' => 0]), HttpMethod::POST);
    }

    /**
     * Delete price alert.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/alerts-delete
     */
    public function delete(array $params): array
    {
        return $this->call('togglePriceAlert', array_merge($params, ['del' => 1]), HttpMethod::POST);
    }

    /**
     * List price alerts.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/alerts-get-list
     */
    public function list(): array
    {
        return $this->call('getAlertsList', [], HttpMethod::POST);
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresSid(): bool
    {
        return false;
    }
}
