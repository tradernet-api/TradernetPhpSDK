<?php

declare(strict_types=1);

/**
 * Shared bootstrap for examples.
 *
 * The SDK itself never loads .env files: that is the responsibility of the
 * host application. Examples use vlucas/phpdotenv (a dev dependency) so that
 * `php examples/*.php` works with a local .env or tradernet.ini.
 */

use Dotenv\Dotenv;
use Tradernet\Sdk\Tradernet;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

if (is_file($root . '/.env') && class_exists(Dotenv::class)) {
    Dotenv::createImmutable($root)->safeLoad();
}

if (isset($_ENV['TRADERNET_API_KEY'], $_ENV['TRADERNET_API_SECRET'])) {
    return Tradernet::fromEnv();
}

if (is_file($root . '/tradernet.ini')) {
    return Tradernet::fromIni($root . '/tradernet.ini');
}

fwrite(
    STDERR,
    "No credentials found.\n"
    . "Create .env (see .env.example) or tradernet.ini (see tradernet.ini.example),\n"
    . "or export TRADERNET_API_KEY and TRADERNET_API_SECRET.\n",
);
exit(1);
