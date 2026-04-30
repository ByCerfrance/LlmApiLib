<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\LlmDecoratorTrait;
use ByCerfrance\LlmApiLib\LlmInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Usage\Usage;
use Override;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversTrait(LlmDecoratorTrait::class)]
#[UsesClass(Usage::class)]
#[UsesClass(Capability::class)]
#[UsesClass(SelectionStrategy::class)]
class LlmDecoratorTraitTest extends TestCase
{
    private function createDecorator(LlmInterface $inner): LlmInterface
    {
        return new class($inner) implements LlmInterface {
            use LlmDecoratorTrait;

            public function __construct(private readonly LlmInterface $provider)
            {
            }

            #[Override]
            public function getProvider(): LlmInterface
            {
                return $this->provider;
            }

            #[Override]
            public function chat(
                CompletionInterface|string $completion,
                ?LoggerInterface $logger = null,
            ): CompletionResponseInterface {
                return $this->provider->chat($completion, $logger);
            }
        };
    }

    public function testGetProvider(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $decorator = $this->createDecorator($inner);

        $this->assertSame($inner, $decorator->getProvider());
    }

    public function testGetMaxContextTokens(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getMaxContextTokens')->willReturn(128000);

        $this->assertSame(128000, $this->createDecorator($inner)->getMaxContextTokens());
    }

    public function testGetMaxContextTokensNull(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getMaxContextTokens')->willReturn(null);

        $this->assertNull($this->createDecorator($inner)->getMaxContextTokens());
    }

    public function testGetMaxOutputTokens(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getMaxOutputTokens')->willReturn(16384);

        $this->assertSame(16384, $this->createDecorator($inner)->getMaxOutputTokens());
    }

    public function testGetMaxOutputTokensNull(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getMaxOutputTokens')->willReturn(null);

        $this->assertNull($this->createDecorator($inner)->getMaxOutputTokens());
    }

    public function testGetScoring(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getScoring')->willReturn(0.75);

        $this->assertSame(0.75, $this->createDecorator($inner)->getScoring(SelectionStrategy::BALANCED));
    }

    public function testGetUsage(): void
    {
        $usage = new Usage(promptTokens: 10, completionTokens: 20, totalTokens: 30);

        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getUsage')->willReturn($usage);

        $this->assertSame($usage, $this->createDecorator($inner)->getUsage());
    }

    public function testGetCost(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getCost')->willReturn(0.0042);

        $this->assertSame(0.0042, $this->createDecorator($inner)->getCost());
    }

    public function testGetCapabilities(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getCapabilities')->willReturn([Capability::TEXT, Capability::IMAGE]);

        $this->assertSame([Capability::TEXT, Capability::IMAGE], $this->createDecorator($inner)->getCapabilities());
    }

    public function testSupports(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('supports')->willReturn(true);

        $this->assertTrue($this->createDecorator($inner)->supports(Capability::TEXT, Capability::IMAGE));
    }

    public function testSupportsReturnsFalse(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('supports')->willReturn(false);

        $this->assertFalse($this->createDecorator($inner)->supports(Capability::VIDEO));
    }

    public function testGetLabels(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getLabels')->willReturn(['summarize', 'classification']);

        $this->assertSame(['summarize', 'classification'], $this->createDecorator($inner)->getLabels());
    }

    public function testGetLabelsEmpty(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getLabels')->willReturn([]);

        $this->assertSame([], $this->createDecorator($inner)->getLabels());
    }

    public function testGetId(): void
    {
        $inner = $this->createMock(LlmInterface::class);
        $inner->method('getId')->willReturn('OpenAi.gpt-4o');

        $this->assertSame('OpenAi.gpt-4o', $this->createDecorator($inner)->getId());
    }
}
