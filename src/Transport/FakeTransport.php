<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Transport;

use Throwable;
use Tradernet\Sdk\Auth\Session;
use Tradernet\Sdk\Exception\ApiErrorException;

/**
 * In-memory transport for unit tests.
 */
final class FakeTransport implements TransportInterface
{
    /** @var list<array{command: string, data: array<string, mixed>, method: HttpMethod, session: ?Session, attachSid: bool}> */
    public array $calls = [];

    /** @var list<ApiErrorException|array<string, mixed>|Throwable> */
    private array $queue = [];

    /**
     * Enqueue a successful response.
     *
     * @param array<string, mixed> $response
     */
    public function enqueue(array $response): void
    {
        $this->queue[] = $response;
    }

    /**
     * Enqueue an exception to throw.
     */
    public function enqueueException(Throwable $exception): void
    {
        $this->queue[] = $exception;
    }

    /**
     * {@inheritdoc}
     */
    public function request(
        string $command,
        array $data = [],
        HttpMethod $method = HttpMethod::POST,
        ?Session $session = null,
        bool $attachSid = true,
    ): array {
        $this->calls[] = [
            'command' => $command,
            'data' => $data,
            'method' => $method,
            'session' => $session,
            'attachSid' => $attachSid,
        ];

        if ($this->queue === []) {
            return [];
        }

        $next = array_shift($this->queue);
        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }
}
