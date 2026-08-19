<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Transport\HttpMethod;

/**
 * Reference commands that exist as real V3 API endpoints.
 *
 * Static documentation tables (markets list, CPS types, order statuses, …) are
 * not API commands and are intentionally omitted.
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class ReferenceApi extends AbstractResource
{
    /**
     * Reception / office public info.
     *
     * @param int $reception Reception id
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/reception-types
     */
    public function receptionInfo(int $reception): array
    {
        return $this->call(
            'getReceptionInfo',
            ['reception' => $reception],
            HttpMethod::POST,
        );
    }

    /**
     * Securities directory / export helper command.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/securities
     */
    public function securities(array $params = []): array
    {
        return $this->call('getAllSecurities', $params, HttpMethod::POST);
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresSid(): bool
    {
        return false;
    }
}
