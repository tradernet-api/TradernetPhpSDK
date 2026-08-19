<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Transport\HttpMethod;

/**
 * Currency rates.
 *
 * @see https://tradernet.com/tradernet-api/currency
 */
final class CurrencyApi extends AbstractResource
{
    /**
     * Cross rates for date.
     *
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/cross-rates-for-date
     */
    public function crossRatesForDate(string $date, array $extra = []): array
    {
        return $this->call(
            'getCrossRatesForDate',
            array_merge(['date' => $date], $extra),
            HttpMethod::POST,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresSid(): bool
    {
        return false;
    }
}
