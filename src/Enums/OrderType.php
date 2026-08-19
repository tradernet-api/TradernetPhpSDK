<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Enums;

/**
 * Order type: market or limit.
 */
enum OrderType: int
{
    case LIMIT = 2;
    case MARKET = 1;
}
