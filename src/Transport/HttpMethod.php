<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Transport;

/**
 * HTTP method for V3 command requests.
 */
enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
}
