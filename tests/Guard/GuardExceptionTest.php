<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Guard;

use ByCerfrance\LlmApiLib\Chat\ChatResponseInterface;
use ByCerfrance\LlmApiLib\Guard\GuardException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(GuardException::class)]
class GuardExceptionTest extends TestCase
{
    public function testMessage(): void
    {
        $response = $this->createStub(ChatResponseInterface::class);
        $exception = new GuardException('Response rejected', $response);

        $this->assertSame('Response rejected', $exception->getMessage());
    }

    public function testGetResponse(): void
    {
        $response = $this->createStub(ChatResponseInterface::class);
        $exception = new GuardException('Response rejected', $response);

        $this->assertSame($response, $exception->getResponse());
    }

    public function testExtendsRuntimeException(): void
    {
        $response = $this->createStub(ChatResponseInterface::class);
        $exception = new GuardException('test', $response);

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }
}
