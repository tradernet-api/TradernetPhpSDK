<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Exception;

use RuntimeException;

/**
 * Business-level API error (`error` / `errMsg` in JSON body).
 */
class ApiErrorException extends RuntimeException implements TradernetExceptionInterface
{
    /**
     * @param null|int|string $errorCode API business error code
     * @param array<string, mixed> $payload Raw response body
     * @param null|int $httpStatus HTTP status the error arrived with
     */
    public function __construct(
        string $message,
        public readonly int|string|null $errorCode = null,
        /** @var array<string, mixed> */
        public readonly array $payload = [],
        public readonly ?int $httpStatus = null,
    ) {
        $code = is_int($errorCode) ? $errorCode : 0;
        parent::__construct($message, $code);
    }

    /**
     * Whether the error indicates an expired or invalid SID.
     *
     * Heuristics are best-effort: server messages are localized. Prefer explicit
     * SID / session wording. Bare HTTP 401 is treated as dead SID only when the
     * message is empty or generic ("access denied"); API-key and security-session
     * errors are never treated as a dead SID.
     */
    public function isSessionDead(): bool
    {
        $message = strtolower($this->getMessage());

        if (
            str_contains($message, 'api key')
            || str_contains($message, 'signature')
            || str_contains($message, 'security session')
        ) {
            return false;
        }

        if (preg_match('/\bsid\b/', $message) === 1) {
            return true;
        }

        if (
            preg_match('/\bsession\b.*\b(expired|invalid|dead)\b/', $message) === 1
            || preg_match('/\b(expired|invalid|dead)\b.*\bsession\b/', $message) === 1
        ) {
            return true;
        }

        if ($this->httpStatus === 401) {
            return $message === ''
                || str_contains($message, 'access denied')
                || str_contains($message, 'not authorized')
                || str_contains($message, 'unauthorized');
        }

        return str_contains($message, 'not authorized')
            || str_contains($message, 'unauthorized');
    }
}
