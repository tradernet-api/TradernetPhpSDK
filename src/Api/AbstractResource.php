<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Auth\Session;
use Tradernet\Sdk\Auth\SessionManager;
use Tradernet\Sdk\Config\AuthMode;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Exception\ApiErrorException;
use Tradernet\Sdk\Exception\AuthenticationException;
use Tradernet\Sdk\Exception\ConfigurationException;
use Tradernet\Sdk\Exception\RateLimitException;
use Tradernet\Sdk\Exception\ReauthBlockedException;
use Tradernet\Sdk\Transport\HttpMethod;
use Tradernet\Sdk\Transport\TransportInterface;

/**
 * Base resource with SID-aware request helper.
 *
 * SID is attached only when {@see requiresSid()} is true. The server resolves
 * `tryBySid() ?: tryByApiKey()`, so a lazy SID cookie silently switches identity
 * away from the API key and breaks API-key security sessions used by orders.
 */
abstract class AbstractResource
{
    /**
     * Commands that must not be blindly re-submitted after re-auth.
     *
     * @var list<string>
     */
    private const array NON_IDEMPOTENT_COMMANDS = [
        'putTradeOrder',
        'delTradeOrder',
        'putStopLoss',
        'putPacketTradeOrder',
        'authByLogin',
        'authBySms',
        'submitCps',
    ];

    /**
     * Resource dependencies.
     */
    public function __construct(
        protected readonly TransportInterface $transport,
        protected readonly SessionManager $sessions,
        protected readonly ClientConfig $config,
    ) {}

    /**
     * Executes a V3 command with at most one re-auth retry.
     *
     * @param array<string, mixed> $data
     * @param null|bool $requiresSid Override resource default
     *
     * @throws ApiErrorException
     * @throws AuthenticationException
     * @throws ConfigurationException
     * @throws ReauthBlockedException
     *
     * @return array<string, mixed>
     */
    protected function call(
        string $command,
        array $data = [],
        HttpMethod $method = HttpMethod::POST,
        ?bool $requiresSid = null,
    ): array {
        $needsSid = $requiresSid ?? $this->requiresSid();
        $lastException = null;
        $rateLimitRetried = false;

        for ($attempt = 0; $attempt <= 1; ++$attempt) {
            $session = $this->resolveSession($needsSid, $attempt > 0);

            try {
                return $this->transport->request(
                    $command,
                    $data,
                    $method,
                    $session,
                    $session !== null,
                );
            } catch (RateLimitException $e) {
                if (
                    !$rateLimitRetried
                    && !$this->isNonIdempotent($command)
                    && $e->retryAfterSeconds !== null
                    && $e->retryAfterSeconds <= 5
                ) {
                    $rateLimitRetried = true;
                    usleep($e->retryAfterSeconds * 1_000_000);
                    --$attempt;

                    continue;
                }

                throw $e;
            } catch (ApiErrorException $e) {
                $lastException = $e;
                if (
                    $attempt === 0
                    && $needsSid
                    && $session !== null
                    && $e->isSessionDead()
                    && $this->config->authMode !== AuthMode::KEYS_ONLY
                    && $this->sessions->canReauthenticate()
                    && !$this->isNonIdempotent($command)
                ) {
                    $this->sessions->invalidate();

                    continue;
                }

                throw $e;
            }
        }

        throw $lastException ?? new ApiErrorException('Request failed after re-authentication');
    }

    /**
     * Whether this resource requires SID by default.
     */
    abstract protected function requiresSid(): bool;

    private function isNonIdempotent(string $command): bool
    {
        return in_array($command, self::NON_IDEMPOTENT_COMMANDS, true);
    }

    /**
     * Resolve SID session for request.
     *
     * When `$needsSid` is false the SDK never attaches a SID cookie, even if a
     * session is already cached: API-key identity must win for those commands.
     *
     * @throws AuthenticationException
     * @throws ConfigurationException
     * @throws ReauthBlockedException
     */
    private function resolveSession(bool $needsSid, bool $forceRefresh): ?Session
    {
        if ($this->config->authMode === AuthMode::KEYS_ONLY) {
            if ($needsSid) {
                throw new ConfigurationException('SID is required but auth mode is keys_only');
            }

            return null;
        }

        if (!$needsSid) {
            return null;
        }

        if ($forceRefresh) {
            return $this->sessions->authenticate();
        }

        return $this->sessions->ensureSession(true);
    }
}
