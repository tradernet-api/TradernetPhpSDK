<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Api;

use Tradernet\Sdk\Transport\HttpMethod;

/**
 * Quotes and tickers.
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class QuotesApi extends AbstractResource
{
    /**
     * Check whether instrument is allowed for trading.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/check-allowed-ticker-and-ban-on-trade
     */
    public function checkAllowed(string $ticker): array
    {
        return $this->call(
            'checkAllowedTickerAndBanOnTrade',
            ['ticker' => $ticker],
            HttpMethod::POST,
        );
    }

    /**
     * Ticker search.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/quotes-finder
     */
    public function find(string $text): array
    {
        return $this->call('tickerFinder', ['text' => $text], HttpMethod::POST);
    }

    /**
     * Real-time quotes.
     *
     * @param list<string>|string $tickers
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/quotes-get
     */
    public function get(array|string $tickers): array
    {
        $list = is_array($tickers) ? $tickers : [$tickers];

        return $this->call(
            'getStockQuotesJson',
            ['tickers' => implode(',', $list)],
            HttpMethod::POST,
        );
    }

    /**
     * Historical candles (HLOC).
     *
     * @param int|string $from Unix timestamp or date string
     * @param int|string $to Unix timestamp or date string
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/quotes-get-hloc
     */
    public function hloc(
        string $ticker,
        int|string $from,
        int|string $to,
        ?int $timeframe = null,
    ): array {
        $params = [
            'id' => $ticker,
            'count' => -1,
            'timeStart' => $from,
            'timeEnd' => $to,
        ];
        if ($timeframe !== null) {
            $params['timeframe'] = $timeframe;
        }

        return $this->call('getHloc', $params, HttpMethod::POST);
    }

    /**
     * Instrument details.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/quotes-get-info
     */
    public function info(string $ticker, bool $sup = true): array
    {
        return $this->call(
            'getSecurityInfo',
            [
                'ticker' => $ticker,
                'sup' => $sup,
            ],
            HttpMethod::POST,
        );
    }

    /**
     * Market status.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/market-status
     */
    public function marketStatus(): array
    {
        return $this->call('getMarketStatus', [], HttpMethod::POST);
    }

    /**
     * Options chain.
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/get-options-by-mkt
     */
    public function options(string $mktName, string $baseAsset): array
    {
        return $this->call(
            'getOptionsByMktNameAndBaseAsset',
            [
                'mktName' => $mktName,
                'baseAsset' => $baseAsset,
            ],
            HttpMethod::POST,
        );
    }

    /**
     * Most traded securities.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @see https://tradernet.com/tradernet-api/quotes-get-top-securities
     */
    public function top(array $params = []): array
    {
        return $this->call('getTopSecurities', $params, HttpMethod::POST);
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresSid(): bool
    {
        return false;
    }
}
