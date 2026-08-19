<?php

declare(strict_types=1);
use Tradernet\Sdk\Tradernet;

/**
 * Current orders example.
 *
 * WARNING: uncommenting the buy() call places a real order in production.
 */

/** @var Tradernet $tn */
$tn = require __DIR__ . '/bootstrap.php';

// $result = $tn->orders()->buy('AAPL.US', quantity: 1, price: 0.0, duration: 'day');
// echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

$orders = $tn->orders()->current(active: true);
echo json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
