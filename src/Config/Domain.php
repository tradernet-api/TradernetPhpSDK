<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Config;

use Tradernet\Sdk\Exception\ConfigurationException;

/**
 * Validates and normalizes Tradernet API base URLs.
 */
final class Domain
{
    /**
     * Known production cabinets (exact host or subdomain).
     *
     * @var list<string>
     */
    public const array ALLOWED_HOST_SUFFIXES = [
        'tradernet.com',
        'tradernet.global',
        'tradernet.kz',
        'freedom24.com',
    ];

    public static function isAllowedHost(string $host): bool
    {
        $host = strtolower($host);
        foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws ConfigurationException
     */
    public static function normalize(string $domain): string
    {
        $trimmed = rtrim(trim($domain), '/');
        if ($trimmed === '') {
            throw new ConfigurationException('API domain must not be empty');
        }

        $parts = parse_url($trimmed);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new ConfigurationException('API domain must be an absolute HTTPS URL');
        }

        if (strtolower($parts['scheme']) !== 'https') {
            throw new ConfigurationException('API domain must use HTTPS');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new ConfigurationException('API domain must not contain credentials');
        }

        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new ConfigurationException('API domain must not contain query or fragment');
        }

        $path = $parts['path'] ?? '';
        if ($path !== '' && $path !== '/') {
            throw new ConfigurationException('API domain must not contain a path');
        }

        $host = strtolower($parts['host']);
        if ($host === '' || str_contains($host, '..')) {
            throw new ConfigurationException('API domain host is invalid');
        }

        if (!self::isAllowedHost($host)) {
            throw new ConfigurationException(
                'API domain host is not in the Tradernet allowlist: ' . $host,
            );
        }

        return 'https://' . $host;
    }
}
