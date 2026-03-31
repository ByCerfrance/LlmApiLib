<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Guard;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionResponse;
use ByCerfrance\LlmApiLib\Guard\GuardException;
use ByCerfrance\LlmApiLib\Usage\Usage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(GuardException::class)]
#[UsesClass(Completion::class)]
#[UsesClass(CompletionResponse::class)]
#[UsesClass(Usage::class)]
class GuardExceptionTest extends TestCase
{
    public function testMessage(): void
    {
        $response = new CompletionResponse(new Completion([]), new Usage());
        $exception = new GuardException('Response rejected', $response);

        $this->assertSame('Response rejected', $exception->getMessage());
    }

    public function testGetResponse(): void
    {
        $response = new CompletionResponse(new Completion([]), new Usage());
        $exception = new GuardException('Response rejected', $response);

        $this->assertSame($response, $exception->getResponse());
    }

    public function testExtendsRuntimeException(): void
    {
        $response = new CompletionResponse(new Completion([]), new Usage());
        $exception = new GuardException('test', $response);

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }
}
