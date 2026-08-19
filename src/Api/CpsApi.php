<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Transport\HttpMethod;

/**
 * Client CPS / requests history.
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class CpsApi extends AbstractResource
{
    /**
     * CPS attached files.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/get-cps-files
     */
    public function files(array $params = []): array
    {
        return $this->call('getCpsFiles', $params, HttpMethod::POST, true);
    }

    /**
     * CPS history.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/get-client-cps-history
     */
    public function history(array $params = []): array
    {
        return $this->call('getClientCpsHistory', $params, HttpMethod::POST, true);
    }

    /**
     * Submit a CPS document.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function submit(int $typeDocId, array $params = []): array
    {
        return $this->call(
            'submitCps',
            array_merge(['type_doc_id' => $typeDocId], $params),
            HttpMethod::POST,
            true,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresSid(): bool
    {
        return true;
    }
}
