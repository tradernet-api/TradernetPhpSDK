<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Auth;

use DateTimeImmutable;
use JsonSerializable;
use Tradernet\Sdk\Exception\ConfigurationException;
use Tradernet\Sdk\Support\Cast;

/**
 * Persisted SID session bound to a login.
 *
 * Treat SID as a password-equivalent bearer token.
 */
final readonly class Session implements JsonSerializable
{
    /**
     * @param string $sid Session ID value
     * @param string $sidName Cookie name (SID / SIDBETA)
     * @param string $login Login used to obtain SID
     * @param string $domain API domain
     */
    public function __construct(
        public string $sid,
        public string $sidName,
        public ?int $userId,
        public string $login,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public string $domain,
    ) {
        if ($sid === '') {
            throw new ConfigurationException('Session SID must not be empty');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'sid' => '***',
            'sidName' => $this->sidName,
            'userId' => $this->userId,
            'login' => $this->login,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'expiresAt' => $this->expiresAt->format(DateTimeImmutable::ATOM),
            'domain' => $this->domain,
        ];
    }

    /**
     * Whether local TTL has elapsed.
     */
    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    /**
     * Soft revalidation threshold (90% of TTL).
     *
     * Optional hook for callers; the SDK does not auto-refresh on this signal.
     */
    public function needsSoftRevalidation(DateTimeImmutable $now, float $ratio = 0.9): bool
    {
        $created = $this->createdAt->getTimestamp();
        $expires = $this->expiresAt->getTimestamp();
        $ttl = max(1, $expires - $created);
        $threshold = $created + (int) floor($ttl * $ratio);

        return $now->getTimestamp() >= $threshold;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sid' => $this->sid,
            'sidName' => $this->sidName,
            'userId' => $this->userId,
            'login' => $this->login,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'expiresAt' => $this->expiresAt->format(DateTimeImmutable::ATOM),
            'domain' => $this->domain,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $sid = Cast::string($data['sid'] ?? '');
        if ($sid === '') {
            throw new ConfigurationException('Stored session is missing SID');
        }

        $userId = $data['userId'] ?? null;

        return new self(
            sid: $sid,
            sidName: Cast::string($data['sidName'] ?? 'SID', 'SID'),
            userId: $userId === null ? null : Cast::int($userId),
            login: Cast::string($data['login'] ?? ''),
            createdAt: new DateTimeImmutable(Cast::string($data['createdAt'] ?? 'now')),
            expiresAt: new DateTimeImmutable(Cast::string($data['expiresAt'] ?? 'now')),
            domain: Cast::string($data['domain'] ?? ''),
        );
    }
}
