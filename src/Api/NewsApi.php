<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Transport\HttpMethod;

/**
 * News providers and articles.
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class NewsApi extends AbstractResource
{
    /**
     * News article detail.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/get-news-detail
     */
    public function detail(int|string $newsId): array
    {
        return $this->call('getNewsDetail', ['id' => $newsId], HttpMethod::POST);
    }

    /**
     * News list.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/get-news-list
     */
    public function list(array $params = []): array
    {
        return $this->call('getNewsList', $params, HttpMethod::POST);
    }

    /**
     * News providers list.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/get-news-providers-list
     */
    public function providers(): array
    {
        return $this->call('getNewsProvidersList', [], HttpMethod::POST);
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresSid(): bool
    {
        return false;
    }
}
