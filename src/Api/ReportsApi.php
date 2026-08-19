<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Transport\HttpMethod;

/**
 * Broker / depositary reports.
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class ReportsApi extends AbstractResource
{
    /**
     * Broker report.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/broker-report
     */
    public function broker(array $params): array
    {
        return $this->call('getBrokerReport', $params, HttpMethod::POST, true);
    }

    /**
     * Build direct broker report URL (host from config).
     *
     * @param array<string, scalar> $query
     *
     * @see https://tradernet.com/tradernet-api/broker-report-url
     */
    public function brokerUrl(array $query): string
    {
        return rtrim($this->config->domain, '/') . '/reports/broker?' . http_build_query($query);
    }

    /**
     * Depositary report.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/depositary-report
     */
    public function depositary(array $params): array
    {
        return $this->call('getDepositaryReport', $params, HttpMethod::POST, true);
    }

    /**
     * Build direct depositary report URL.
     *
     * @param array<string, scalar> $query
     *
     * @see https://tradernet.com/tradernet-api/broker-depositary-report-url
     */
    public function depositaryUrl(array $query): string
    {
        return rtrim($this->config->domain, '/') . '/reports/depositary?' . http_build_query($query);
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresSid(): bool
    {
        return true;
    }
}
