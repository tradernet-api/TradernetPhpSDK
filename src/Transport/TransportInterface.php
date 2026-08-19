<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Transport;

use Tradernet\Sdk\Auth\Session;
use Tradernet\Sdk\Exception\ApiErrorException;
use Tradernet\Sdk\Exception\InvalidResponseException;
use Tradernet\Sdk\Exception\RateLimitException;
use Tradernet\Sdk\Exception\TransportException;

/**
 * HTTP transport for signed Tradernet V3 commands.
 */
interface TransportInterface
{
    /**
     * Sends a signed V3 API request.
     *
     * @param string $command API command name (path segment)
     * @param array<string, mixed> $data Request parameters
     * @param HttpMethod $method HTTP method
     * @param null|Session $session Optional SID session cookies
     * @param bool $attachSid Whether to attach SID cookie from session
     *
     * @throws ApiErrorException
     * @throws InvalidResponseException
     * @throws RateLimitException
     * @throws TransportException
     *
     * @return array<string, mixed>
     */
    public function request(
        string $command,
        array $data = [],
        HttpMethod $method = HttpMethod::POST,
        ?Session $session = null,
        bool $attachSid = true,
    ): array;
}
