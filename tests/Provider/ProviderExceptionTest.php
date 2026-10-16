<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use Berlioz\Http\Message\Response;
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
