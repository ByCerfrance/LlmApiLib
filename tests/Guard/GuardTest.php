<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Guard;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionResponse;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Guard\Guard;
use ByCerfrance\LlmApiLib\Guard\GuardException;
use ByCerfrance\LlmApiLib\LlmDecoratorTrait;
use ByCerfrance\LlmApiLib\LlmInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Usage\Usage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Guard::class)]
#[UsesTrait(LlmDecoratorTrait::class)]
#[UsesClass(GuardException::class)]
#[UsesClass(Completion::class)]
#[UsesClass(CompletionResponse::class)]
#[UsesClass(Usage::class)]
class GuardTest extends TestCase
{
    public function testChatPassesThroughWhenGuardDoesNotThrow(): void
    {
        $expected = new CompletionResponse(new Completion([]), new Usage());

        $inner = $this->createMock(LlmInterface::class);
        $inner->method('chat')->willReturn($expected);

        $guard = new Guard(
            $inner,
            function (CompletionResponseInterface $response): void {
                // no-op: guard passes
            },
        );

        $result = $guard->chat('hello');

        $this->assertSame($expected, $result);
    }

    public function testChatThrowsGuardExceptionWhenGuardThrows(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('chat')->willReturn(
            new CompletionResponse(new Completion([]), new Usage()),
        );

        $guard = new Guard(
            $inner,
            function (CompletionResponseInterface $response): void {
                throw new RuntimeException('Guard check failed');
            },
        );

        $this->expectException(GuardException::class);
        $this->expectExceptionMessage('Guard check failed');

        $guard->chat('hello');
    }

    public function testGuardExceptionCarriesResponse(): void
    {
        $expected = new CompletionResponse(new Completion([]), new Usage());

        $inner = $this->createMock(LlmInterface::class);
        $inner->method('chat')->willReturn($expected);

        $guard = new Guard(
            $inner,
            function (CompletionResponseInterface $response): void {
                throw new RuntimeException('Rejected');
            },
        );

        try {
            $guard->chat('hello');
            $this->fail('Expected GuardException');
        } catch (GuardException $e) {
            $this->assertSame($expected, $e->getResponse());
            $this->assertSame('Rejected', $e->getMessage());
        }
    }

    public function testCustomGuardOnUsage(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('chat')->willReturn(
            new CompletionResponse(
                new Completion([]),
                new Usage(promptTokens: 5000, completionTokens: 6000, totalTokens: 11000),
            ),
        );

        $guard = new Guard(
            $inner,
            function (CompletionResponseInterface $response): void {
                if ($response->getUsage()->getTotalTokens() > 10000) {
                    throw new RuntimeException('Token budget exceeded');
                }
            },
        );

        $this->expectException(GuardException::class);
        $this->expectExceptionMessage('Token budget exceeded');

        $guard->chat('hello');
    }

    public function testDelegatesGetMaxContextTokens(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getMaxContextTokens')->willReturn(128000);

        $guard = new Guard($inner, function (): void {
        });

        $this->assertSame(128000, $guard->getMaxContextTokens());
    }

    public function testDelegatesGetUsage(): void
    {
        $usage = new Usage(promptTokens: 10, completionTokens: 20, totalTokens: 30);

        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getUsage')->willReturn($usage);

        $guard = new Guard($inner, function (): void {
        });

        $this->assertSame($usage, $guard->getUsage());
    }

    public function testDelegatesGetCost(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getCost')->willReturn(0.0042);

        $guard = new Guard($inner, function (): void {
        });

        $this->assertSame(0.0042, $guard->getCost());
    }

    public function testDelegatesGetCapabilities(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getCapabilities')->willReturn([]);

        $guard = new Guard($inner, function (): void {
        });

        $this->assertSame([], $guard->getCapabilities());
    }

    public function testDelegatesSupports(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('supports')->willReturn(true);

        $guard = new Guard($inner, function (): void {
        });

        $this->assertTrue($guard->supports(Capability::TEXT));
    }
}
