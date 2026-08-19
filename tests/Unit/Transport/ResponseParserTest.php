<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Tradernet\Sdk\Exception\ApiErrorException;
use Tradernet\Sdk\Exception\InvalidResponseException;
use Tradernet\Sdk\Exception\RateLimitException;
use Tradernet\Sdk\Transport\ResponseParser;

final class ResponseParserTest extends TestCase
{
    public function testBareSessionWordIsNotSessionDead(): void
    {
        $e = new ApiErrorException('session module unavailable', null, [], 403);
        self::assertFalse($e->isSessionDead());
    }

    public function testErrorResponseWithHtmlBodyBecomesInvalidResponse(): void
    {
        $parser = new ResponseParser();

        $this->expectException(InvalidResponseException::class);
        $parser->throwForErrorResponse('<h1>502 Bad Gateway</h1>', 502);
    }

    public function testErrorResponseWithJsonBodyBecomesApiError(): void
    {
        $parser = new ResponseParser();

        try {
            $parser->throwForErrorResponse('{"error":"Invalid api key provided","code":null}', 403);
        } catch (ApiErrorException $e) {
            self::assertSame('Invalid api key provided', $e->getMessage());
            self::assertSame(403, $e->httpStatus);
        }
    }

    public function testFalsyErrorFieldIsSuccess(): void
    {
        $parser = new ResponseParser();

        self::assertSame(
            ['error' => false, 'ok' => true],
            $parser->parse('{"error":false,"ok":true}', 200),
        );
        self::assertSame(
            ['error' => '', 'data' => 1],
            $parser->parse('{"error":"","data":1}', 200),
        );
        self::assertSame(
            ['error' => 0, 'data' => 1],
            $parser->parse('{"error":0,"data":1}', 200),
        );
        self::assertSame(
            ['error' => '0', 'data' => 1],
            $parser->parse('{"error":"0","data":1}', 200),
        );
    }

    public function testInvalidApiKeyIsNotSessionDead(): void
    {
        $e = new ApiErrorException('Invalid api key provided', null, [], 403);
        self::assertFalse($e->isSessionDead());
    }

    public function testParsesJsonObject(): void
    {
        $parser = new ResponseParser();
        $result = $parser->parse('{"ok":true}', 200);

        self::assertSame(['ok' => true], $result);
    }

    public function testRateLimitIncludesRetryAfterSeconds(): void
    {
        $parser = new ResponseParser();

        try {
            $parser->parse('', 429, ['Retry-After' => ['3']]);
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(3, $e->retryAfterSeconds);
        }
    }

    public function testRateLimitWithoutRetryAfter(): void
    {
        $parser = new ResponseParser();

        try {
            $parser->parse('', 429);
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertNull($e->retryAfterSeconds);
        }
    }

    public function testResidSubstringIsNotSessionDead(): void
    {
        $e = new ApiErrorException('Invalid resid provided', null, [], 403);
        self::assertFalse($e->isSessionDead());
    }

    public function testSecuritySessionRequiredIsNotSessionDead(): void
    {
        $e = new ApiErrorException('you need to open a security session', null, [], 403);
        self::assertFalse($e->isSessionDead());
    }

    public function testSessionDeadDetection(): void
    {
        $e = new ApiErrorException('SID expired', 7);
        self::assertTrue($e->isSessionDead());
    }

    public function testThrowsOnBusinessError(): void
    {
        $parser = new ResponseParser();

        $this->expectException(ApiErrorException::class);
        $parser->parse('{"error":"boom","code":7}', 200);
    }

    public function testThrowsOnErrMsg(): void
    {
        $parser = new ResponseParser();

        $this->expectException(ApiErrorException::class);
        $parser->parse('{"errMsg":"fail","code":1}', 200);
    }

    public function testThrowsOnNonJson(): void
    {
        $parser = new ResponseParser();

        $this->expectException(InvalidResponseException::class);
        $parser->parse('<h1>error</h1>', 200);
    }

    public function testUnauthorized401WithUnrelatedMessageIsNotSessionDead(): void
    {
        $e = new ApiErrorException('Upstream maintenance window', null, [], 401);
        self::assertFalse($e->isSessionDead());
    }

    public function testUnauthorizedStatusMeansSessionDead(): void
    {
        $e = new ApiErrorException('Access denied', null, [], 401);
        self::assertTrue($e->isSessionDead());
    }
}
