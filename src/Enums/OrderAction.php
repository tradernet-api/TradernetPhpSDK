<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Enums;

/**
 * Order side / action id as used by putTradeOrder.
 */
enum OrderAction: int
{
    case BUY = 1;
    case BUY_MARGIN = 2;
    case SELL = 3;
    case SELL_SHORT = 4;
}
