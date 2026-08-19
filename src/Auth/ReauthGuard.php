<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Auth;

use DateTimeImmutable;
use Tradernet\Sdk\Clock\ClockInterface;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Exception\ReauthBlockedException;
use Tradernet\Sdk\Support\Cast;

/**
 * Persistent circuit breaker for authByLogin attempts.
 */
final class ReauthGuard
{
    private bool $authenticating = false;

    public function __construct(
        private readonly SessionStorageInterface $storage,
        private readonly ClientConfig $config,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Checks whether authByLogin is allowed for storage key.
     *
     * @throws ReauthBlockedException
     */
    public function allow(string $key): void
    {
        $now = $this->clock->now();
        $meta = $this->storage->loadMeta($key) ?? [];

        $openUntil = Cast::int($meta['openUntil'] ?? 0);
        if ($openUntil > $now) {
            throw new ReauthBlockedException(
                sprintf(
                    'Re-authentication circuit is open until %s',
                    (new DateTimeImmutable('@' . $openUntil))->format(DateTimeImmutable::ATOM),
                ),
            );
        }

        $windowStart = Cast::int($meta['windowStart'] ?? 0);
        $attempts = Cast::int($meta['attempts'] ?? 0);

        if ($windowStart === 0 || ($now - $windowStart) > $this->config->reauthWindowSeconds) {
            return;
        }

        if ($attempts >= $this->config->reauthMaxAttempts) {
            throw new ReauthBlockedException(
                sprintf(
                    'Re-authentication limit exceeded (%d attempts / %d seconds)',
                    $this->config->reauthMaxAttempts,
                    $this->config->reauthWindowSeconds,
                ),
            );
        }
    }

    /**
     * Backoff delay in seconds for attempt number (1-based).
     */
    public function backoffSeconds(int $attemptNumber): int
    {
        $base = 4 ** max(0, $attemptNumber - 1);

        return min(16, max(1, $base));
    }

    /**
     * Marks start of authentication; throws if nested.
     *
     * @throws ReauthBlockedException
     */
    public function begin(): void
    {
        if ($this->authenticating) {
            throw new ReauthBlockedException('Nested re-authentication is not allowed');
        }

        $this->authenticating = true;
    }

    /**
     * Clears in-process authenticating flag.
     */
    public function end(): void
    {
        $this->authenticating = false;
    }

    /**
     * Records a login attempt and may open the circuit.
     *
     * @param bool $openImmediately Force open (permanent auth failure)
     */
    public function recordAttempt(string $key, bool $openImmediately = false): void
    {
        $now = $this->clock->now();
        $meta = $this->storage->loadMeta($key) ?? [];
        $windowStart = Cast::int($meta['windowStart'] ?? 0);
        $attempts = Cast::int($meta['attempts'] ?? 0);

        if ($windowStart === 0 || ($now - $windowStart) > $this->config->reauthWindowSeconds) {
            $windowStart = $now;
            $attempts = 0;
        }

        ++$attempts;

        $meta = [
            'windowStart' => $windowStart,
            'attempts' => $attempts,
            'openUntil' => Cast::int($meta['openUntil'] ?? 0),
        ];

        if ($openImmediately || $attempts >= $this->config->reauthMaxAttempts) {
            $meta['openUntil'] = $now + $this->config->reauthOpenSeconds;
        }

        $this->storage->saveMeta($key, $meta);
    }

    /**
     * Clears attempt counters after successful login.
     */
    public function reset(string $key): void
    {
        $this->storage->saveMeta($key, [
            'windowStart' => 0,
            'attempts' => 0,
            'openUntil' => 0,
        ]);
    }
}
