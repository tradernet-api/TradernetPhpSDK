<?php

declare(strict_types=1);
use Tradernet\Sdk\Tradernet;

/**
 * Quotes example.
 *
 * Usage:
 *   php examples/quotes.php
 *
 * Credentials are taken from .env, tradernet.ini or the environment.
 */

/** @var Tradernet $tn */
$tn = require __DIR__ . '/bootstrap.php';

$quotes = $tn->quotes()->get(['AAPL.US', 'FRHC.US']);

echo json_encode($quotes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
