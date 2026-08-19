<?php

declare(strict_types=1);

/**
 * Credentials diagnostics.
 *
 * Every Tradernet installation has its own api_keys storage, and a key issued
 * in one cabinet is unknown to the others. This script replays a single cheap
 * command against each known domain and reports where the key is accepted.
 *
 * Usage:
 *   php examples/diagnose.php
 *   TRADERNET_DOMAINS=https://tradernet.global,https://tradernet.kz php examples/diagnose.php
 */

use Dotenv\Dotenv;
use Tradernet\Sdk\Config\AuthMode;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Config\ConfigFactory;
use Tradernet\Sdk\Config\Credentials;
use Tradernet\Sdk\Exception\ApiErrorException;
use Tradernet\Sdk\Exception\TradernetExceptionInterface;
use Tradernet\Sdk\Tradernet;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (is_file($root . '/.env') && class_exists(Dotenv::class)) {
    Dotenv::createImmutable($root)->safeLoad();
}

if (isset($_ENV['TRADERNET_API_KEY'], $_ENV['TRADERNET_API_SECRET'])) {
    [$credentials, $config] = ConfigFactory::fromEnv();
} elseif (is_file($root . '/tradernet.ini')) {
    [$credentials, $config] = ConfigFactory::fromIni($root . '/tradernet.ini');
} else {
    fwrite(STDERR, "No credentials found: create .env or tradernet.ini first.\n");
    exit(1);
}

$domainsRaw = $_ENV['TRADERNET_DOMAINS'] ?? getenv('TRADERNET_DOMAINS');
$domains = is_string($domainsRaw) && $domainsRaw !== ''
    ? array_map(trim(...), explode(',', $domainsRaw))
    : [
        'https://tradernet.com',
        'https://tradernet.global',
        'https://tradernet.kz',
        'https://freedom24.com',
    ];

if (!in_array($config->domain, $domains, true)) {
    array_unshift($domains, $config->domain);
}

printf(
    "Public key fingerprint: %s…%s (%d chars)\n\n",
    substr($credentials->apiKey(), 0, 4),
    substr($credentials->apiKey(), -4),
    strlen($credentials->apiKey()),
);

$accepted = [];

foreach ($domains as $domain) {
    $verdict = probe($credentials, $config, $domain);
    printf("%-28s %s\n", $domain, $verdict['message']);

    if ($verdict['accepted']) {
        $accepted[] = $domain;
    }
}

echo "\n";

if ($accepted === []) {
    echo "The key was rejected everywhere. Most likely it was revoked or issued\n"
        . "on an installation outside this list. Re-issue it in the cabinet you\n"
        . "actually use, or pass TRADERNET_DOMAINS with that domain.\n";
    exit(1);
}

printf("Key is valid on: %s\n", implode(', ', $accepted));

if (!in_array($config->domain, $accepted, true)) {
    printf("Set TRADERNET_DOMAIN=%s in your .env.\n", $accepted[0]);
}

/**
 * Runs one signed command against a domain and classifies the outcome.
 *
 * @return array{accepted: bool, message: string}
 */
function probe(Credentials $credentials, ClientConfig $config, string $domain): array
{
    $probeConfig = new ClientConfig(
        domain: $domain,
        lang: $config->lang,
        authMode: AuthMode::KEYS_ONLY,
        timeout: 10.0,
    );

    try {
        $sdk = new Tradernet($credentials, $probeConfig);
        $sdk->quotes()->get(['AAPL.US']);

        return ['accepted' => true, 'message' => 'OK — key accepted'];
    } catch (ApiErrorException $e) {
        $message = $e->getMessage();
        $known = str_contains(strtolower($message), 'invalid api key');

        return [
            // A signature or account complaint still proves the key exists here.
            'accepted' => !$known && $e->httpStatus !== 401,
            'message' => sprintf('HTTP %s — %s', $e->httpStatus ?? '???', $message),
        ];
    } catch (TradernetExceptionInterface $e) {
        return ['accepted' => false, 'message' => 'unreachable — ' . $e->getMessage()];
    }
}
