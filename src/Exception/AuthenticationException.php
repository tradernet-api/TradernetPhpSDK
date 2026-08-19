<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Exception;

use RuntimeException;
use Throwable;

/**
 * Login / SID acquisition failure.
 */
class AuthenticationException extends RuntimeException implements TradernetExceptionInterface
{
    public const int CODE_PERMANENT = 1;
    public const int CODE_TRANSIENT = 0;

    public function __construct(
        string $message,
        int $code = self::CODE_TRANSIENT,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Permanent auth failure that must not be retried.
     */
    public function isPermanent(): bool
    {
        return $this->code === self::CODE_PERMANENT;
    }
}
