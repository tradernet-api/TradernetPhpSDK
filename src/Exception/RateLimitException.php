<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Exception;

use RuntimeException;

/**
 * HTTP 429 / rate-limit response from Tradernet API.
 */
class RateLimitException extends RuntimeException implements TradernetExceptionInterface
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message, 429);
    }
}
