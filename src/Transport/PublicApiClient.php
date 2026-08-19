<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use SensitiveParameter;
use Tradernet\Sdk\Auth\Session;
use Tradernet\Sdk\Clock\ClockInterface;
use Tradernet\Sdk\Clock\SystemClock;
use Tradernet\Sdk\Config\ClientConfig;
use Tradernet\Sdk\Config\Domain;
use Tradernet\Sdk\Exception\ConfigurationException;
use Tradernet\Sdk\Exception\TransportException;

/**
 * Tradernet Public API V3 transport client (Guzzle).
 *
 * @see https://tradernet.com/tradernet-api/
 */
final class PublicApiClient implements TransportInterface
{
    private Client $client;

    private ClockInterface $clock;

    /** @var array<string, string> */
    private array $cookies = [];

    private ResponseParser $parser;

    private Signer $signer;

    /**
     * @param string $apiPublicKey Public API key
     * @param string $apiSecretKey Private API key used for request signing
     * @param string $apiHost Tradernet HTTPS origin without `/api` (allowlisted)
     * @param null|Client $client Injectable Guzzle client (tests). Caller must pin
     *                            `base_uri` to an allowlisted host and disable redirects.
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
        ?Client $client = null,
        float $timeout = 30.0,
        private readonly string $userAgent = ClientConfig::DEFAULT_USER_AGENT,
    ) {
        $normalizedHost = Domain::normalize($apiHost);
        $baseUri = $normalizedHost . '/api/';
        $this->client = $client ?? new Client([
            'base_uri' => $baseUri,
            'http_errors' => false,
            'timeout' => $timeout,
            'allow_redirects' => false,
        ]);
        $this->signer = new Signer($apiSecretKey);
        $this->parser = new ResponseParser();
        $this->clock = $clock ?? new SystemClock();
    }

    /**
     * @return array<string, string>
     */
    public function getCookies(): array
    {
        return $this->cookies;
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
        if (str_contains($command, '://') || str_contains($command, '..') || str_starts_with($command, '/')) {
            throw new ConfigurationException('Absolute or traversing API commands are not allowed');
        }

        $timestamp = $this->clock->now();
        $cookies = $this->cookies;

        if ($attachSid && $session !== null) {
            $cookies[$session->sidName] = $session->sid;
        }

        if ($method === HttpMethod::GET) {
            $signatureData = (string) $timestamp;
            $options = [RequestOptions::QUERY => $data];
            $headers = [];
        } else {
            $body = $this->signer->stringify($data);
            $signatureData = $body . $timestamp;
            $options = [RequestOptions::BODY => $body];
            $headers = ['Content-Type' => 'application/json'];
        }

        $headers = array_merge($headers, [
            'User-Agent' => $this->userAgent,
            'X-NtApi-PublicKey' => $this->apiPublicKey,
            'X-NtApi-Sig' => $this->signer->sign($signatureData),
            'X-NtApi-Timestamp' => (string) $timestamp,
        ]);

        if ($cookies !== []) {
            $parts = [];
            foreach ($cookies as $name => $value) {
                $parts[] = $name . '=' . $value;
            }
            $headers['Cookie'] = implode('; ', $parts);
        }

        $options[RequestOptions::HEADERS] = $headers;

        try {
            $response = $this->client->request($method->value, $command, $options);
        } catch (GuzzleException $e) {
            throw new TransportException(
                'Tradernet API transport error: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        $raw = $response->getBody()->getContents();
        $statusCode = $response->getStatusCode();
        /** @var array<string, list<string>> $responseHeaders */
        $responseHeaders = $response->getHeaders();

        if ($statusCode >= 400 && $statusCode !== 429) {
            $this->parser->throwForErrorResponse($raw, $statusCode);
        }

        return $this->parser->parse($raw, $statusCode, $responseHeaders);
    }

    /**
     * Mutating language helper for fluent bootstrap.
     */
    public function setLanguage(string $lang): self
    {
        $this->cookies['lang'] = $lang;

        return $this;
    }

    /**
     * Sets or replaces a cookie value.
     */
    public function withCookie(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->cookies = $this->cookies;
        $clone->cookies[$name] = $value;

        return $clone;
    }

    /**
     * Sets language cookie.
     *
     * @param string $lang ISO 639-1 code
     */
    public function withLanguage(string $lang): self
    {
        return $this->withCookie('lang', $lang);
    }

    /**
     * Sets SID cookie.
     */
    public function withSid(string $sidValue, string $sidName = 'SID'): self
    {
        return $this->withCookie($sidName, $sidValue);
    }
}
