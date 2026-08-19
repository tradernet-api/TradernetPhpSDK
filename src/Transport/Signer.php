<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Transport;

use JsonException;
use SensitiveParameter;

/**
 * HMAC-SHA256 request signer for Tradernet API V3.
 */
final class Signer
{
    /**
     * @param string $secretKey Private API key
     */
    public function __construct(
        #[SensitiveParameter]
        private readonly string $secretKey,
    ) {}

    /**
     * Signs payload for POST/PUT (body + timestamp) or GET (timestamp only).
     *
     * @param string $signatureData Data to HMAC
     */
    public function sign(string $signatureData): string
    {
        return hash_hmac('sha256', $signatureData, $this->secretKey);
    }

    /**
     * Compact JSON used for request body and signature input.
     *
     * @param array<string, mixed> $data
     *
     * @throws JsonException
     */
    public function stringify(array $data): string
    {
        if ($data === []) {
            return '';
        }

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
