<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Ws;

/**
 * Parsed WebSocket frame `[event, data]`.
 */
final readonly class Frame
{
    /**
     * @param array<int, mixed> $raw
     */
    public function __construct(
        public string $event,
        public mixed $data,
        public array $raw,
    ) {}

    /**
     * Builds a frame from a decoded `[event, data]` payload.
     *
     * @param array<int, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $event = isset($payload[0]) && is_scalar($payload[0]) ? (string) $payload[0] : '';
        $data = $payload[1] ?? null;

        return new self($event, $data, $payload);
    }
}
