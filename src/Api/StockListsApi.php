<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Transport\HttpMethod;

/**
 * User watchlists / stock lists.
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class StockListsApi extends AbstractResource
{
    /**
     * Create watchlist.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/quotes-add-list
     */
    public function add(string $name): array
    {
        return $this->call('addStockList', ['name' => $name], HttpMethod::POST);
    }

    /**
     * Add ticker to watchlist.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/quotes-add-list-ticker
     */
    public function addTicker(int|string $listId, string $ticker): array
    {
        return $this->call(
            'addStockListTicker',
            [
                'id' => $listId,
                'ticker' => $ticker,
            ],
            HttpMethod::POST,
        );
    }

    /**
     * Delete watchlist.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/quotes-delete-list
     */
    public function delete(int|string $listId): array
    {
        return $this->call('deleteStockList', ['id' => $listId], HttpMethod::POST);
    }

    /**
     * Remove ticker from watchlist.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/quotes-delete-list-ticker
     */
    public function deleteTicker(int|string $listId, string $ticker): array
    {
        return $this->call(
            'deleteStockListTicker',
            [
                'id' => $listId,
                'ticker' => $ticker,
            ],
            HttpMethod::POST,
        );
    }

    /**
     * User watchlists.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/quotes-get-lists
     */
    public function list(): array
    {
        return $this->call('getUserStockLists', [], HttpMethod::POST);
    }

    /**
     * Select active watchlist.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/quotes-make-list-selected
     */
    public function select(int|string $listId): array
    {
        return $this->call('makeStockListSelected', ['id' => $listId], HttpMethod::POST);
    }

    /**
     * Update watchlist.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/quotes-update-list
     */
    public function update(int|string $listId, array $params): array
    {
        return $this->call(
            'updateStockList',
            array_merge(['id' => $listId], $params),
            HttpMethod::POST,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresSid(): bool
    {
        return false;
    }
}
