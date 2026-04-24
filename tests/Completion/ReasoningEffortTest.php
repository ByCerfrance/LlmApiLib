<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion;

use ByCerfrance\LlmApiLib\Completion\ReasoningEffort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReasoningEffort::class)]
class ReasoningEffortTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('none', ReasoningEffort::NONE->value);
        $this->assertSame('low', ReasoningEffort::LOW->value);
        $this->assertSame('medium', ReasoningEffort::MEDIUM->value);
        $this->assertSame('high', ReasoningEffort::HIGH->value);
        $this->assertSame('xhigh', ReasoningEffort::XHIGH->value);
    }

    public function testFromString(): void
    {
        $this->assertSame(ReasoningEffort::NONE, ReasoningEffort::from('none'));
        $this->assertSame(ReasoningEffort::LOW, ReasoningEffort::from('low'));
        $this->assertSame(ReasoningEffort::MEDIUM, ReasoningEffort::from('medium'));
        $this->assertSame(ReasoningEffort::HIGH, ReasoningEffort::from('high'));
        $this->assertSame(ReasoningEffort::XHIGH, ReasoningEffort::from('xhigh'));
    }

    public function testTryFromReturnsNullForUnknown(): void
    {
        $this->assertNull(ReasoningEffort::tryFrom('unknown'));
        $this->assertNull(ReasoningEffort::tryFrom(''));
    }

    public function testTryFromReturnsEnumForKnown(): void
    {
        $this->assertSame(ReasoningEffort::HIGH, ReasoningEffort::tryFrom('high'));
        $this->assertSame(ReasoningEffort::NONE, ReasoningEffort::tryFrom('none'));
    }

    public function testCaseCount(): void
    {
        $this->assertCount(5, ReasoningEffort::cases());
    }

    public function testFallback(): void
    {
        $this->assertSame(ReasoningEffort::HIGH, ReasoningEffort::XHIGH->fallback());
        $this->assertSame(ReasoningEffort::MEDIUM, ReasoningEffort::HIGH->fallback());
        $this->assertSame(ReasoningEffort::LOW, ReasoningEffort::MEDIUM->fallback());
        $this->assertSame(ReasoningEffort::NONE, ReasoningEffort::LOW->fallback());
        $this->assertNull(ReasoningEffort::NONE->fallback());
    }

    public function testFallbackChain(): void
    {
        $effort = ReasoningEffort::XHIGH;
        $chain = [];

        while (null !== $effort) {
            $chain[] = $effort;
            $effort = $effort->fallback();
        }

        $this->assertSame(
            [
                ReasoningEffort::XHIGH,
                ReasoningEffort::HIGH,
                ReasoningEffort::MEDIUM,
                ReasoningEffort::LOW,
                ReasoningEffort::NONE,
            ],
            $chain,
        );
    }
}
