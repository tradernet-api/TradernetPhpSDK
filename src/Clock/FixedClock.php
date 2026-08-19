<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Clock;

/**
 * Deterministic clock for tests.
 */
final class FixedClock implements ClockInterface
{
    /**
     * @param int $timestamp Fixed Unix timestamp
     */
    public function __construct(
        private int $timestamp,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function now(): int
    {
        return $this->timestamp;
    }

    /**
     * Moves the clock to a new timestamp.
     */
    public function set(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }
}
