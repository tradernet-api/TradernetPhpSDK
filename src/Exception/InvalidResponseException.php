<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Exception;

use RuntimeException;

/**
 * Response body was not valid JSON or had an unexpected shape.
 */
class InvalidResponseException extends RuntimeException implements TradernetExceptionInterface {}
