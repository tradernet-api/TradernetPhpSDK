<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use InvalidArgumentException;
use Tradernet\Sdk\Enums\OrderAction;
use Tradernet\Sdk\Enums\OrderExpiration;
use Tradernet\Sdk\Enums\OrderType;
use Tradernet\Sdk\Transport\HttpMethod;

/**
 * Orders / trade commands.
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class OrdersApi extends AbstractResource
{
    /**
     * Market/limit buy helper.
     *
     * @param string $duration day|ext|gtc
     *
     * @return array<string, mixed>
     */
    public function buy(
        string $ticker,
        float|int $quantity = 1,
        float $price = 0.0,
        string $duration = 'day',
        bool $useMargin = false,
        ?int $userOrderId = null,
    ): array {
        $action = $useMargin ? OrderAction::BUY_MARGIN : OrderAction::BUY;

        return $this->put(
            $ticker,
            $action,
            $quantity,
            $price,
            OrderExpiration::fromName($duration),
            $userOrderId,
        );
    }

    /**
     * Cancel order.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/orders-delete
     */
    public function cancel(int|string $orderId): array
    {
        return $this->call('delTradeOrder', ['order_id' => $orderId], HttpMethod::POST);
    }

    /**
     * Current / active orders.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/orders-get-current-history
     */
    public function current(bool $active = true): array
    {
        return $this->call(
            'getNotifyOrderJson',
            ['active' => $active],
            HttpMethod::POST,
        );
    }

    /**
     * Orders history for a period.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/get-orders-history
     */
    public function history(array $params = []): array
    {
        return $this->call('getOrdersHistory', $params, HttpMethod::POST);
    }

    /**
     * Place a trade order.
     *
     * @param float $price 0 = market
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/orders-send
     */
    public function put(
        string $ticker,
        OrderAction $action,
        float|int $quantity,
        float $price = 0.0,
        OrderExpiration $expiration = OrderExpiration::DAY,
        ?int $userOrderId = null,
    ): array {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Order quantity must be positive');
        }
        if ($price < 0) {
            throw new InvalidArgumentException('Order price must be non-negative');
        }

        $params = [
            'instr_name' => $ticker,
            'action_id' => $action->value,
            'order_type_id' => $price !== 0.0 ? OrderType::LIMIT->value : OrderType::MARKET->value,
            'qty' => $quantity,
            'limit_price' => $price,
            'expiration_id' => $expiration->value,
        ];
        if ($userOrderId !== null) {
            $params['user_order_id'] = $userOrderId;
        }

        return $this->call('putTradeOrder', $params, HttpMethod::POST);
    }

    /**
     * Market/limit sell helper (owned position). Does not open a short.
     *
     * @return array<string, mixed>
     */
    public function sell(
        string $ticker,
        float|int $quantity = 1,
        float $price = 0.0,
        string $duration = 'day',
        ?int $userOrderId = null,
    ): array {
        return $this->put(
            $ticker,
            OrderAction::SELL,
            $quantity,
            $price,
            OrderExpiration::fromName($duration),
            $userOrderId,
        );
    }

    /**
     * Short sell helper.
     *
     * @return array<string, mixed>
     */
    public function short(
        string $ticker,
        float|int $quantity = 1,
        float $price = 0.0,
        string $duration = 'day',
        ?int $userOrderId = null,
    ): array {
        return $this->put(
            $ticker,
            OrderAction::SELL_SHORT,
            $quantity,
            $price,
            OrderExpiration::fromName($duration),
            $userOrderId,
        );
    }

    /**
     * Stop Loss / Take Profit.
     *
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/stop-loss
     */
    public function stopLoss(string $ticker, float $price, array $extra = []): array
    {
        return $this->call(
            'putStopLoss',
            array_merge(
                [
                    'instr_name' => $ticker,
                    'stop_loss' => $price,
                ],
                $extra,
            ),
            HttpMethod::POST,
        );
    }

    /**
     * Trades history.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/get-trades-history
     */
    public function tradesHistory(array $params = []): array
    {
        return $this->call('getTradesHistory', $params, HttpMethod::POST);
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresSid(): bool
    {
        return false;
    }
}
