<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderException::class)]
#[UsesClass(FailureKind::class)]
#[UsesClass(TokenUsage::class)]
class ProviderExceptionTest extends TestCase
{
    public static function statusProvider(): iterable
    {
        yield 'unauthorized' => [401, FailureKind::AUTHENTICATION, false];
        yield 'forbidden' => [403, FailureKind::AUTHENTICATION, false];
        yield 'rate limit' => [429, FailureKind::RATE_LIMIT, true];
        yield 'timeout' => [408, FailureKind::UNAVAILABLE, true];
        yield 'unavailable' => [503, FailureKind::UNAVAILABLE, true];
        yield 'invalid request' => [422, FailureKind::INVALID_REQUEST, false];
        yield 'unknown' => [302, FailureKind::UNKNOWN, false];
    }

    #[DataProvider('statusProvider')]
    public function testFromHttpStatusClassifiesFailures(int $status, FailureKind $kind, bool $transient): void
    {
        $exception = ProviderException::fromHttpStatus('provider', $status);

        self::assertSame($kind, $exception->getKind());
        self::assertSame($status, $exception->getCode());
        self::assertSame($transient, $exception->isTransient());
        self::assertSame('provider', $exception->getProviderId());
        self::assertNull($exception->getRequestId());
        self::assertNull($exception->getRetryAfterMs());
    }

    public function testFromResponseRetainsOnlySafeMetadata(): void
    {
        $exception = ProviderException::fromResponse('provider', new Response(
            '{"error":"secret prompt","usage":{"prompt_tokens":4,"completion_tokens":1,"total_tokens":5}}',
            429,
            headers: ['Retry-After' => '3', 'x-request-id' => 'req-1'],
        ));

        self::assertSame(3000, $exception->getRetryAfterMs());
        self::assertSame('req-1', $exception->getRequestId());
        self::assertSame(5, $exception->getReportedUsage()?->getTotalTokens());
        self::assertStringNotContainsString('secret', $exception->getMessage());
        self::assertNull($exception->getProviderReason());
    }

    /** @return iterable<string, array{string, int, FailureKind, FinishReason|null, string|null}> */
    public static function errorCodeProvider(): iterable
    {
        yield 'OpenAI image moderation' => ['{"error":{"code":"moderation_blocked","message":"Your prompt..."}}', 400, FailureKind::CONTENT_REFUSED, FinishReason::CONTENT_FILTER, 'moderation_blocked'];
        yield 'DALL-E content policy' => ['{"error":{"code":"content_policy_violation"}}', 400, FailureKind::CONTENT_REFUSED, FinishReason::CONTENT_FILTER, 'content_policy_violation'];
        yield 'other code' => ['{"error":{"code":"invalid_value"}}', 400, FailureKind::INVALID_REQUEST, null, 'invalid_value'];
        yield 'Google status' => ['{"error":{"code":400,"status":"INVALID_ARGUMENT"}}', 400, FailureKind::INVALID_REQUEST, null, 'INVALID_ARGUMENT'];
        yield 'refusal code on server error' => ['{"error":{"code":"content_filter"}}', 500, FailureKind::UNAVAILABLE, null, 'content_filter'];
        yield 'free text is discarded' => ['{"error":{"code":"secret prompt text"}}', 400, FailureKind::INVALID_REQUEST, null, null];
    }

    #[DataProvider('errorCodeProvider')]
    public function testFromResponseKeepsTheErrorCode(string $body, int $status, FailureKind $kind, ?FinishReason $reason, ?string $code): void
    {
        $exception = ProviderException::fromResponse('provider', new Response($body, $status));

        self::assertSame($kind, $exception->getKind());
        self::assertSame($reason, $exception->getFinishReason());
        self::assertSame($code, $exception->getProviderReason());
    }

    public function testInvalidRetryAfterIsIgnored(): void
    {
        self::assertNull(ProviderException::fromHttpStatus('provider', 503, 'later')->getRetryAfterMs());
    }

    public function testResponseWithoutUsageReportsNone(): void
    {
        self::assertNull(ProviderException::fromResponse('provider', new Response('not JSON', 500))->getReportedUsage());
    }
}
