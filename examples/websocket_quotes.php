<?php

declare(strict_types=1);

/**
 * WebSocket quotes stream.
 *
 * The stream is authenticated by SID, not by API keys: without a SID the
 * server answers with demo data and refuses the orders/sessions channels.
 *
 * Usage:
 *   composer require amphp/websocket-client
 *
 *   php examples/websocket_quotes.php                  # demo data, no login needed
 *   TRADERNET_WS_SID=1 php examples/websocket_quotes.php   # real account data
 *   TRADERNET_WS_SID=1 php examples/websocket_quotes.php SBER.RU FRHC.US
 *
 * Real data additionally requires TRADERNET_LOGIN and TRADERNET_PASSWORD:
 * the SDK obtains a SID via authByLogin and reuses it for two weeks.
 */

use Tradernet\Sdk\Tradernet;
use Tradernet\Sdk\Ws\Amp\AmpWebSocketClient;

/** @var Tradernet $tn */
$tn = require __DIR__ . '/bootstrap.php';

if (!function_exists('Amp\Websocket\Client\connect')) {
    fwrite(STDERR, "Install amphp/websocket-client first: composer require amphp/websocket-client\n");
    exit(1);
}

$sidFlag = $_ENV['TRADERNET_WS_SID'] ?? getenv('TRADERNET_WS_SID');
$requireSid = in_array($sidFlag, ['1', 'true', 'yes'], true);

$tickers = array_slice($argv, 1);
if ($tickers === []) {
    $tickers = ['AAPL.US'];
}

printf(
    "Connecting to %s (%s), tickers: %s\n",
    $tn->config()->websocketUrl(),
    $requireSid ? 'SID: real account data' : 'no SID: demo data',
    implode(', ', $tickers),
);

$ws = $tn->websocket(requireSid: $requireSid);

if (!$ws instanceof AmpWebSocketClient) {
    fwrite(STDERR, "Unexpected WebSocket client implementation\n");
    exit(1);
}

$ws->connect();
$ws->quotes($tickers);

$received = 0;

foreach ($ws->frames() as $frame) {
    if ($frame->event === 'userData' && is_array($frame->data)) {
        printf("userData: mode=%s\n", json_encode($frame->data['mode'] ?? null));

        continue;
    }

    echo $frame->event . ' ' . json_encode($frame->data, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    if (++$received >= 5) {
        break;
    }
}

$ws->close();
