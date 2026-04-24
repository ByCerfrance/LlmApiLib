<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion;

use ByCerfrance\LlmApiLib\Completion\ServiceTier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceTier::class)]
class ServiceTierTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('auto', ServiceTier::AUTO->value);
        $this->assertSame('default', ServiceTier::DEFAULT->value);
        $this->assertSame('flex', ServiceTier::FLEX->value);
        $this->assertSame('priority', ServiceTier::PRIORITY->value);
    }

    public function testFromString(): void
    {
        $this->assertSame(ServiceTier::AUTO, ServiceTier::from('auto'));
        $this->assertSame(ServiceTier::DEFAULT, ServiceTier::from('default'));
        $this->assertSame(ServiceTier::FLEX, ServiceTier::from('flex'));
        $this->assertSame(ServiceTier::PRIORITY, ServiceTier::from('priority'));
    }

    public function testTryFromReturnsNullForUnknown(): void
    {
        $this->assertNull(ServiceTier::tryFrom('unknown'));
        $this->assertNull(ServiceTier::tryFrom(''));
    }

    public function testTryFromReturnsEnumForKnown(): void
    {
        $this->assertSame(ServiceTier::AUTO, ServiceTier::tryFrom('auto'));
        $this->assertSame(ServiceTier::FLEX, ServiceTier::tryFrom('flex'));
    }

    public function testCaseCount(): void
    {
        $this->assertCount(4, ServiceTier::cases());
    }

    public function testFallback(): void
    {
        $this->assertSame(ServiceTier::AUTO, ServiceTier::PRIORITY->fallback());
        $this->assertSame(ServiceTier::AUTO, ServiceTier::FLEX->fallback());
        $this->assertSame(ServiceTier::DEFAULT, ServiceTier::AUTO->fallback());
        $this->assertNull(ServiceTier::DEFAULT->fallback());
    }

    public function testFallbackChain(): void
    {
        $tier = ServiceTier::PRIORITY;
        $chain = [];

        while (null !== $tier) {
            $chain[] = $tier;
            $tier = $tier->fallback();
        }

        $this->assertSame(
            [ServiceTier::PRIORITY, ServiceTier::AUTO, ServiceTier::DEFAULT],
            $chain,
        );
    }
}
