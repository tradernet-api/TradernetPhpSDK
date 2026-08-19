<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Transport\HttpMethod;

/**
 * Shop catalog.
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class ShopApi extends AbstractResource
{
    /**
     * Shop catalog.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/shop-get-shop-catalog
     */
    public function catalog(array $params = []): array
    {
        return $this->call('getShopCatalog', $params, HttpMethod::POST);
    }

    /**
     * Shop stock data.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/shop-get-stock-data
     */
    public function stockData(array $params = []): array
    {
        return $this->call('getStockData', $params, HttpMethod::POST);
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresSid(): bool
    {
        return false;
    }
}
