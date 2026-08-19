<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Exception;

use RuntimeException;

/**
 * Re-authentication circuit breaker is open (too many authByLogin attempts).
 */
class ReauthBlockedException extends RuntimeException implements TradernetExceptionInterface {}
