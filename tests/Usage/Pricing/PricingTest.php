<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Usage\Pricing;

use ByCerfrance\LlmApiLib\Usage\Pricing\Cost;
use ByCerfrance\LlmApiLib\Usage\Pricing\Pricing;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pricing::class)]
#[UsesClass(Cost::class)]
#[UsesClass(TokenUsage::class)]
class PricingTest extends TestCase
{
    public function testDifferentInputRatesRequireAKnownCacheCount(): void
    {
        $pricing = new Pricing('USD', input: 1, output: 2, cachedInput: 0.1);
        $usage = new TokenUsage(2124, 241, 2365);
        $this->assertNull($pricing->estimate($usage)->amount);
    }

    public function testReferenceCostDoesNotChangeObservedUsage(): void
    {
        $pricing = new Pricing('USD', input: 1, output: 2, cachedInput: 1);
        $usage = new TokenUsage(2124, 241, 2365);
        $cost = $pricing->estimate($usage);
        $this->assertEqualsWithDelta(0.002606, $cost->amount, 1e-12);
        $this->assertSame('USD', $cost->currency);
        $this->assertSame(2124, $usage->getPromptTokens());
        $this->assertSame(241, $usage->getCompletionTokens());
        $this->assertSame(2365, $usage->getTotalTokens());
        $this->assertNull($usage->getCachedTokens());
        $this->assertNull((new Pricing('USD', input: 1, output: 2, cachedInput: 0.1))->estimate($usage)->amount);
    }

    public function testReferenceCostWorksWithAnyTokenUsageImplementation(): void
    {
        $usage = $this->createStub(TokenUsageInterface::class);
        $usage->method('getPromptTokens')->willReturn(2124);
        $usage->method('getCompletionTokens')->willReturn(241);
        $usage->method('getCachedTokens')->willReturn(null);
        $pricing = new Pricing('EUR', input: 1, output: 2, cachedInput: 1, perTokens: 1000);
        $this->assertEqualsWithDelta(2.606, $pricing->estimate($usage)->amount, 1e-12);
    }

    /** @return iterable<string, array{?int, float}> */
    public static function knownCacheCounts(): iterable
    {
        yield 'unknown cache gets no discount' => [null, 0.002606];
        yield 'explicit cache miss' => [0, 0.002606];
        yield 'partial cache hit' => [1000, 0.001706];
        yield 'all input cached' => [2124, 0.0006944];
    }

    #[DataProvider('knownCacheCounts')]
    public function testExplicitCacheStillUsesTheConfiguredRate(?int $cached, float $expected): void
    {
        $reference = new Pricing('USD', input: 1, output: 2, cachedInput: 1);
        $discounted = new Pricing('USD', input: 1, output: 2, cachedInput: 0.1);
        $usage = new TokenUsage(2124, 241, 2365, $cached);
        $this->assertEqualsWithDelta(0.002606, $reference->estimate($usage)->amount, 1e-12);
        if ($cached !== null) {
            $this->assertEqualsWithDelta($expected, $discounted->estimate($usage)->amount, 1e-12);
        } else {
            $this->assertNull($discounted->estimate($usage)->amount);
        }
    }

    /** @return iterable<string, array{TokenUsage, Pricing}> */
    public static function unknownUsageOrRates(): iterable
    {
        $pricing = new Pricing('USD', input: 1, output: 2, cachedInput: 1);
        yield 'all usage unknown' => [new TokenUsage(), $pricing];
        yield 'input unknown' => [new TokenUsage(null, 241, 2365), $pricing];
        yield 'output unknown' => [new TokenUsage(2124, null, 2365), $pricing];
        yield 'zero output with unknown input' => [new TokenUsage(null, 0), $pricing];
        yield 'zero input with unknown output' => [new TokenUsage(0), $pricing];
        yield 'input rate unknown' => [new TokenUsage(2124, 241), new Pricing('USD', output: 2)];
        yield 'output rate unknown' => [new TokenUsage(2124, 241), new Pricing('USD', input: 1, cachedInput: 1)];
        yield 'cache hit rate unknown' => [new TokenUsage(2124, 241, 2365, 1000), new Pricing('USD', input: 1, output: 2)];
        yield 'zero rates do not establish consumption' => [new TokenUsage(), new Pricing('USD', input: 0, output: 0, cachedInput: 0)];
    }

    #[DataProvider('unknownUsageOrRates')]
    public function testUnknownUsageOrRatesDoNotBecomeFree(TokenUsage $usage, Pricing $pricing): void
    {
        $this->assertNull($pricing->estimate($usage)->amount);
    }

    public function testKnownZeroTokensNeedNoRatesOrCacheCount(): void
    {
        $this->assertSame(0.0, (new Pricing('USD'))->estimate(new TokenUsage(0, 0))->amount);
    }

    public function testUnknownTotalDoesNotPreventPricingKnownInputAndOutput(): void
    {
        $pricing = new Pricing('USD', input: 1, output: 2, cachedInput: 1);
        $this->assertEqualsWithDelta(0.002606, $pricing->estimate(new TokenUsage(2124, 241))->amount, 1e-12);
    }

    public function testReferenceCostStillRejectsImpossibleCacheCounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Pricing('USD', input: 1, output: 2, cachedInput: 1))->estimate(new TokenUsage(10, 1, 11, 12));
    }
}
