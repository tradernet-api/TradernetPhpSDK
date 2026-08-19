<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Exception;

use RuntimeException;
use Throwable;

/**
 * Network / HTTP-client failure talking to Tradernet.
 */
class TransportException extends RuntimeException implements TradernetExceptionInterface
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
