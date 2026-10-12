<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use ByCerfrance\LlmApiLib\Provider\ProviderException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderException::class)]
class ProviderExceptionTest extends TestCase
{
    public function testGetBodyReturnsShortBodyUntouched(): void
    {
        $exception = new ProviderException('Invalid response', '{"error":"quota exceeded"}');

        self::assertSame('{"error":"quota exceeded"}', $exception->getBody());
    }

    public function testConstructorTruncatesOversizedBody(): void
    {
        $body = str_repeat('a', ProviderException::MAX_BODY_LENGTH + 100);

        $exception = new ProviderException('Invalid response', $body);

        self::assertSame(
            str_repeat('a', ProviderException::MAX_BODY_LENGTH) . '… [truncated]',
            $exception->getBody(),
        );
    }

    public function testBodyAtExactLimitIsNotTruncated(): void
    {
        $body = str_repeat('a', ProviderException::MAX_BODY_LENGTH);

        $exception = new ProviderException('Invalid response', $body);

        self::assertSame($body, $exception->getBody());
    }

    public function testTruncateBodyLeavesShortBodyUntouched(): void
    {
        self::assertSame('short', ProviderException::truncateBody('short'));
    }

    public function testTruncateBodyTruncatesOversizedBody(): void
    {
        $body = str_repeat('z', ProviderException::MAX_BODY_LENGTH + 1);

        self::assertSame(
            str_repeat('z', ProviderException::MAX_BODY_LENGTH) . '… [truncated]',
            ProviderException::truncateBody($body),
        );
    }

    public function testDefaultBodyIsEmptyString(): void
    {
        $exception = new ProviderException('Invalid response');

        self::assertSame('', $exception->getBody());
    }
}
