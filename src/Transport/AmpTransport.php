<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Transport;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use SensitiveParameter;
use Throwable;
use Tradernet\Sdk\Auth\Session;
use Tradernet\Sdk\Clock\ClockInterface;
use Tradernet\Sdk\Clock\SystemClock;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Config\Domain;
use Tradernet\Sdk\Exception\ConfigurationException;
use Tradernet\Sdk\Exception\TransportException;

/**
 * Non-blocking HTTP transport for use inside an Amp event loop.
 *
 * Requires: composer require amphp/http-client
 *
 * Experimental: prefer {@see PublicApiClient} unless you already run Amp.
 * Same security contract: allowlisted HTTPS host, no redirects, path-safe commands.
 */
final class AmpTransport implements TransportInterface
{
    private ClockInterface $clock;

    /** @var array<string, string> */
    private array $cookies = [];

    private ResponseParser $parser;
    private Signer $signer;
    private readonly string $apiHost;

    /**
     * @param string $apiPublicKey Public API key
     * @param string $apiSecretKey Private API key used for request signing
     * @param string $apiHost Tradernet HTTPS origin without `/api` (allowlisted)
     * @param float $timeout Request timeout seconds
     * @param string $userAgent User-Agent sent with every request
     */
    public function __construct(
        #[SensitiveParameter]
        private readonly string $apiPublicKey,
        #[SensitiveParameter]
        string $apiSecretKey,
        string $apiHost = 'https://tradernet.com',
        ?ClockInterface $clock = null,
        private readonly float $timeout = 30.0,
        private readonly string $userAgent = ClientConfig::DEFAULT_USER_AGENT,
    ) {
        $this->apiHost = Domain::normalize($apiHost);
        $this->signer = new Signer($apiSecretKey);
        $this->parser = new ResponseParser();
        $this->clock = $clock ?? new SystemClock();
    }

    /**
     * {@inheritdoc}
     */
    public function request(
        string $command,
        array $data = [],
        HttpMethod $method = HttpMethod::POST,
        ?Session $session = null,
        bool $attachSid = true,
    ): array {
        if (!class_exists(HttpClientBuilder::class)) {
            throw new ConfigurationException(
                'amphp/http-client is required for AmpTransport. '
                . 'Run: composer require amphp/http-client',
            );
        }

        if (
            str_contains($command, '://')
            || str_contains($command, '..')
            || str_starts_with($command, '/')
        ) {
            throw new ConfigurationException('Absolute or traversing API commands are not allowed');
        }

        $timestamp = $this->clock->now();
        $cookies = $this->cookies;
        if ($attachSid && $session !== null) {
            $cookies[$session->sidName] = $session->sid;
        }

        $url = $this->apiHost . '/api/' . $command;
        $headers = [
            'User-Agent' => $this->userAgent,
            'X-NtApi-PublicKey' => $this->apiPublicKey,
            'X-NtApi-Timestamp' => (string) $timestamp,
        ];

        $body = null;
        if ($method === HttpMethod::GET) {
            $headers['X-NtApi-Sig'] = $this->signer->sign((string) $timestamp);
            if ($data !== []) {
                $url .= '?' . http_build_query($data);
            }
        } else {
            $body = $this->signer->stringify($data);
            $headers['X-NtApi-Sig'] = $this->signer->sign($body . $timestamp);
            $headers['Content-Type'] = 'application/json';
        }

        if ($cookies !== []) {
            $parts = [];
            foreach ($cookies as $name => $value) {
                $parts[] = $name . '=' . $value;
            }
            $headers['Cookie'] = implode('; ', $parts);
        }

        try {
            $client = (new HttpClientBuilder())->followRedirects(0)->build();
            $request = new Request($url, $method->value);
            $request->setHeaders($headers);
            $request->setTcpConnectTimeout($this->timeout);
            $request->setTransferTimeout($this->timeout);
            if ($body !== null) {
                $request->setBody($body);
            }

            $response = $client->request($request);
            $raw = $response->getBody()->buffer();
            $status = $response->getStatus();
            $retryAfter = $response->getHeader('retry-after');
            $responseHeaders = $retryAfter !== null
                ? ['Retry-After' => [$retryAfter]]
                : [];
        } catch (Throwable $e) {
            throw new TransportException(
                'Amp transport error: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        if ($status >= 400 && $status !== 429) {
            $this->parser->throwForErrorResponse($raw, $status);
        }

        return $this->parser->parse($raw, $status, $responseHeaders);
    }

    /**
     * Sets language cookie.
     */
    public function setLanguage(string $lang): self
    {
        $this->cookies['lang'] = $lang;

        return $this;
    }
}
