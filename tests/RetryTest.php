<?php

namespace ByCerfrance\LlmApiLib\Tests;

use ByCerfrance\LlmApiLib\Capability;
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionResponse;
use ByCerfrance\LlmApiLib\LlmInterface;
use ByCerfrance\LlmApiLib\Retry;
use ByCerfrance\LlmApiLib\Usage\Usage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RetryTest extends TestCase
{
    public function testGetUsage()
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock->method('getUsage')->willReturn($expected = new Usage());

        $retry = new Retry($mock);
        $this->assertSame($expected, $retry->getUsage());
    }

    public function testChat()
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

    public function testChat_failed()
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

    public function testGetCapabilities()
    {
        $mock = $this->createMock(LlmInterface::class);
        $mock->method('getCapabilities')->willReturn([Capability::DOCUMENT]);

        $this->assertSame($mock->getCapabilities(), (new Retry($mock))->getCapabilities());
    }
}
