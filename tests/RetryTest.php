<?php

namespace ByCerfrance\LlmApiLib\Tests;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionResponse;
use ByCerfrance\LlmApiLib\LlmInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Retry;
use ByCerfrance\LlmApiLib\Usage\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RetryTest extends TestCase
{
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
                $this->callback(fn(array $context) =>
                    $context['attempt'] === 1 &&
                    $context['max_retries'] === 2 &&
                    $context['wait_ms'] === 0 &&
                    $context['exception'] === 'First failure'
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
}
