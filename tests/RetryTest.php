<?php

namespace ByCerfrance\LlmApiLib\Tests;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionResponse;
use ByCerfrance\LlmApiLib\Guard\GuardException;
use ByCerfrance\LlmApiLib\LlmDecoratorTrait;
use ByCerfrance\LlmApiLib\LlmInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Retry;
use ByCerfrance\LlmApiLib\Usage\Usage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(Retry::class)]
#[UsesTrait(LlmDecoratorTrait::class)]
#[UsesClass(Completion::class)]
#[UsesClass(CompletionResponse::class)]
#[UsesClass(GuardException::class)]
#[UsesClass(ProviderException::class)]
#[UsesClass(Usage::class)]
#[UsesClass(Capability::class)]
class RetryTest extends TestCase
{
    public function testGetId(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock->method('getId')->willReturn('OpenAi.gpt-4o');

        $retry = new Retry($mock);
        $this->assertSame('OpenAi.gpt-4o', $retry->getId());
    }

    public function testGetUsage(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock->method('getUsage')->willReturn($expected = new Usage());

        $retry = new Retry($mock);
        $this->assertSame($expected, $retry->getUsage());
    }

    public function testChat(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException()),
                $expected = new CompletionResponse(new Completion([]), new Usage())
            );

        $usleep = microtime(true);
        $retry = new Retry($mock, time: $expectedTime = 200, retry: 2);

        $this->assertSame($expected, $retry->chat(new Completion([])));
        $this->assertGreaterThanOrEqual($expectedTime, round((microtime(true) - $usleep) * 1000));
    }

    public function testChat_failed(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException()),
                $this->throwException(new RuntimeException()),
                new CompletionResponse(new Completion([]), new Usage())
            );

        $retry = new Retry($mock, time: 0, retry: 2);

        $this->expectException(RuntimeException::class);
        $retry->chat(new Completion([]));
    }

    public function testChatLogsRetryAttempts(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException('First failure')),
                new CompletionResponse(new Completion([]), new Usage())
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'LLM retry attempt {attempt}/{max_retries} failed, waiting {wait_ms}ms',
                $this->callback(fn(array $context) => $context['attempt'] === 1 &&
                    $context['max_retries'] === 2 &&
                    $context['wait_ms'] === 0 &&
                    $context['exception'] === 'First failure'
                )
            );

        $retry = new Retry($mock, time: 0, retry: 2);
        $retry->chat(new Completion([]), $logger);
    }

    public function testChatLogsResponseBodyOnProviderException(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new ProviderException('Server error', '{"error":"rate limited"}')),
                new CompletionResponse(new Completion([]), new Usage())
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'LLM retry attempt {attempt}/{max_retries} failed, waiting {wait_ms}ms',
                $this->callback(fn(array $context) => $context['exception'] === 'Server error' &&
                    $context['response_body'] === '{"error":"rate limited"}'
                )
            );

        $retry = new Retry($mock, time: 0, retry: 2);
        $retry->chat(new Completion([]), $logger);
    }

    public function testChatLogsAllRetryAttemptsOnFailure(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException('First failure')),
                $this->throwException(new RuntimeException('Second failure')),
                $this->throwException(new RuntimeException('Third failure')),
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->exactly(3))
            ->method('warning');

        $retry = new Retry($mock, time: 0, retry: 3);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('First failure');
        $retry->chat(new Completion([]), $logger);
    }

    public function testGetMaxContextTokens(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock->method('getMaxContextTokens')->willReturn(128000);

        $this->assertSame(128000, (new Retry($mock))->getMaxContextTokens());
    }

    public function testGetMaxContextTokensNull(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock->method('getMaxContextTokens')->willReturn(null);

        $this->assertNull((new Retry($mock))->getMaxContextTokens());
    }

    public function testGetMaxOutputTokens(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock->method('getMaxOutputTokens')->willReturn(16384);

        $this->assertSame(16384, (new Retry($mock))->getMaxOutputTokens());
    }

    public function testGetMaxOutputTokensNull(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock->method('getMaxOutputTokens')->willReturn(null);

        $this->assertNull((new Retry($mock))->getMaxOutputTokens());
    }

    public function testGetCapabilities(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock->method('getCapabilities')->willReturn([Capability::DOCUMENT]);

        $this->assertSame($mock->getCapabilities(), (new Retry($mock))->getCapabilities());
    }

    public function testSupports(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock->method('supports')->willReturn(true);

        $this->assertTrue((new Retry($mock))->supports(Capability::VIDEO, Capability::REASONING));
    }

    public function testMultiplierBackoff(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException()),
                $this->throwException(new RuntimeException()),
                $expected = new CompletionResponse(new Completion([]), new Usage())
            );

        $start = microtime(true);
        // time=100ms, multiplier=2: wait 100ms (100*2^0) + 200ms (100*2^1) = 300ms
        $retry = new Retry($mock, time: 100, retry: 3, multiplier: 2);
        $result = $retry->chat(new Completion([]));
        $elapsed = round((microtime(true) - $start) * 1000);

        $this->assertSame($expected, $result);
        $this->assertGreaterThanOrEqual(280, $elapsed);
    }

    public function testMultiplierDefaultIsFixedDelay(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException()),
                $expected = new CompletionResponse(new Completion([]), new Usage())
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                $this->anything(),
                $this->callback(fn(array $context) => $context['wait_ms'] === 100)
            );

        $retry = new Retry($mock, time: 100, retry: 2);
        $retry->chat(new Completion([]), $logger);
    }

    public function testMultiplierLogsIncreasingWaitTimes(): void
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException()),
                $this->throwException(new RuntimeException()),
                $this->throwException(new RuntimeException()),
            );

        $waitTimes = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->exactly(3))
            ->method('warning')
            ->with(
                $this->anything(),
                $this->callback(function (array $context) use (&$waitTimes) {
                    $waitTimes[] = $context['wait_ms'];

                    return true;
                })
            );

        $retry = new Retry($mock, time: 100, retry: 3, multiplier: 2);

        try {
            $retry->chat(new Completion([]), $logger);
        } catch (RuntimeException) {
        }

        // 100*2^0=100, 100*2^1=200, 100*2^2=400
        $this->assertSame([100, 200, 400], $waitTimes);
    }

    public function testGuardExceptionNotRetriedByDefault(): void
    {
        $response = new CompletionResponse(new Completion([]), new Usage());
        $guardException = new GuardException('Truncated', $response);

        $mock = $this->createMock(LlmInterface::class);
        $mock
            ->expects($this->once())
            ->method('chat')
            ->willThrowException($guardException);

        $retry = new Retry($mock, time: 0, retry: 3);

        try {
            $retry->chat(new Completion([]));
            $this->fail('Expected GuardException');
        } catch (GuardException $e) {
            $this->assertSame($response, $e->getResponse());
            $this->assertSame('Truncated', $e->getMessage());
        }
    }

    public function testGuardExceptionRetriedWhenEnabled(): void
    {
        $response = new CompletionResponse(new Completion([]), new Usage());
        $expected = new CompletionResponse(new Completion([]), new Usage());

        $mock = $this->createMock(LlmInterface::class);
        $mock
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new GuardException('Truncated', $response)),
                $expected,
            );

        $retry = new Retry($mock, time: 0, retry: 2, retryOnGuard: true);
        $result = $retry->chat(new Completion([]));

        $this->assertSame($expected, $result);
    }

    public function testGuardExceptionNotRetriedThrowsOriginal(): void
    {
        $response = new CompletionResponse(new Completion([]), new Usage());

        $mock = $this->createMock(LlmInterface::class);
        $mock
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new GuardException('Truncated', $response)),
                new CompletionResponse(new Completion([]), new Usage()),
            );

        $retry = new Retry($mock, time: 0, retry: 3, retryOnGuard: false);

        $this->expectException(GuardException::class);
        $this->expectExceptionMessage('Truncated');

        $retry->chat(new Completion([]));
    }
}
