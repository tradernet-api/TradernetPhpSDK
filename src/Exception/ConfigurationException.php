<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Exception;

use RuntimeException;

/**
 * Misconfigured credentials, domain, auth mode, or missing optional package.
 */
class ConfigurationException extends RuntimeException implements TradernetExceptionInterface {}
