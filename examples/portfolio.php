<?php

declare(strict_types=1);

use Tradernet\Sdk\Tradernet;

/** @var Tradernet $tn */
$tn = require __DIR__ . '/bootstrap.php';

// getPositionJson uses API-key identity (requiresSid=false).
// Call $tn->auth()->ensureSession() only when you need a SID for other commands.
$portfolio = $tn->portfolio()->get();
echo json_encode($portfolio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
