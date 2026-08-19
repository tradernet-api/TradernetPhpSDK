<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Transport;

use Tradernet\Sdk\Exception\ApiErrorException;
use Tradernet\Sdk\Exception\InvalidResponseException;
use Tradernet\Sdk\Exception\RateLimitException;

/**
 * Parses HTTP body into array and maps business errors to exceptions.
 */
final class ResponseParser
{
    /**
     * Throws when payload contains a business-level error field.
     *
     * @param array<string, mixed> $payload
     *
     * @throws ApiErrorException
     */
    public function assertNoBusinessError(array $payload, ?int $statusCode = null): void
    {
        if (array_key_exists('error', $payload)) {
            $error = $payload['error'];
            // Falsy / empty "error" means success on several Tradernet endpoints.
            if ($error !== false && $error !== null && $error !== '' && $error !== 0 && $error !== '0') {
                if (is_string($error)) {
                    $code = $payload['code'] ?? null;

                    throw new ApiErrorException(
                        $error,
                        is_int($code) || is_string($code) ? $code : null,
                        $payload,
                        $statusCode,
                    );
                }

                if (is_array($error) && $error !== []) {
                    $code = $payload['code'] ?? null;

                    throw new ApiErrorException(
                        json_encode($error, JSON_THROW_ON_ERROR),
                        is_int($code) || is_string($code) ? $code : null,
                        $payload,
                        $statusCode,
                    );
                }
            }
        }

        if (isset($payload['errMsg']) && is_string($payload['errMsg']) && $payload['errMsg'] !== '') {
            $code = $payload['code'] ?? null;

            throw new ApiErrorException(
                $payload['errMsg'],
                is_int($code) || is_string($code) ? $code : null,
                $payload,
                $statusCode,
            );
        }
    }

    /**
     * @param string $raw Raw response body
     * @param int $statusCode HTTP status
     * @param array<string, list<string>|string> $headers Response headers (optional Retry-After)
     *
     * @throws ApiErrorException
     * @throws InvalidResponseException
     * @throws RateLimitException
     *
     * @return array<string, mixed>
     */
    public function parse(string $raw, int $statusCode, array $headers = []): array
    {
        if ($statusCode === 429) {
            throw new RateLimitException(
                'Tradernet API rate limit exceeded',
                $this->retryAfterSeconds($headers),
            );
        }

        if ($raw === '') {
            return [];
        }

        if (!json_validate($raw)) {
            throw new InvalidResponseException(
                sprintf(
                    'Tradernet API returned non-JSON response (HTTP %d): %s',
                    $statusCode,
                    substr($raw, 0, 200),
                ),
                $statusCode,
            );
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new InvalidResponseException(
                'Tradernet API returned JSON that is not an object/array',
                $statusCode,
            );
        }

        /** @var array<string, mixed> $decoded */
        $this->assertNoBusinessError($decoded, $statusCode);

        return $decoded;
    }

    /**
     * Parses an error response body, always throwing.
     *
     * Falls back to a generic message when the body carries no error field.
     *
     * @throws ApiErrorException
     * @throws InvalidResponseException
     */
    public function throwForErrorResponse(string $raw, int $statusCode): never
    {
        if (json_validate($raw)) {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $this->assertNoBusinessError($decoded, $statusCode);

                throw new ApiErrorException(
                    sprintf('Tradernet API returned HTTP %d', $statusCode),
                    null,
                    $decoded,
                    $statusCode,
                );
            }
        }

        throw new InvalidResponseException(
            sprintf(
                'Tradernet API returned HTTP %d: %s',
                $statusCode,
                substr($raw, 0, 200),
            ),
            $statusCode,
        );
    }

    /**
     * @param array<string, list<string>|string> $headers
     */
    private function retryAfterSeconds(array $headers): ?int
    {
        $raw = null;
        foreach ($headers as $name => $value) {
            if (strcasecmp((string) $name, 'Retry-After') !== 0) {
                continue;
            }
            $raw = is_array($value) ? ($value[0] ?? null) : $value;

            break;
        }

        if (!is_string($raw) && !is_int($raw)) {
            return null;
        }

        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }
}
