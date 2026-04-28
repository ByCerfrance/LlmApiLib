<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion;

use ByCerfrance\LlmApiLib\Completion\FinishReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FinishReason::class)]
class FinishReasonTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('stop', FinishReason::STOP->value);
        $this->assertSame('length', FinishReason::LENGTH->value);
        $this->assertSame('tool_calls', FinishReason::TOOL_CALLS->value);
        $this->assertSame('content_filter', FinishReason::CONTENT_FILTER->value);
    }

    public function testFromString(): void
    {
        $this->assertSame(FinishReason::STOP, FinishReason::from('stop'));
        $this->assertSame(FinishReason::LENGTH, FinishReason::from('length'));
        $this->assertSame(FinishReason::TOOL_CALLS, FinishReason::from('tool_calls'));
        $this->assertSame(FinishReason::CONTENT_FILTER, FinishReason::from('content_filter'));
    }

    public function testTryFromReturnsNullForUnknown(): void
    {
        $this->assertNull(FinishReason::tryFrom('unknown_reason'));
        $this->assertNull(FinishReason::tryFrom(''));
    }

    public function testTryFromReturnsEnumForKnown(): void
    {
        $this->assertSame(FinishReason::STOP, FinishReason::tryFrom('stop'));
        $this->assertSame(FinishReason::LENGTH, FinishReason::tryFrom('length'));
    }

    public function testCaseCount(): void
    {
        $this->assertCount(4, FinishReason::cases());
    }

    public function testParseStandardValues(): void
    {
        $this->assertSame(FinishReason::STOP, FinishReason::parse('stop'));
        $this->assertSame(FinishReason::LENGTH, FinishReason::parse('length'));
        $this->assertSame(FinishReason::TOOL_CALLS, FinishReason::parse('tool_calls'));
        $this->assertSame(FinishReason::CONTENT_FILTER, FinishReason::parse('content_filter'));
    }

    public function testParseCompositeContentFilter(): void
    {
        $this->assertSame(FinishReason::CONTENT_FILTER, FinishReason::parse('content_filter: RECITATION'));
    }

    public function testParseReturnsNullForUnknown(): void
    {
        $this->assertNull(FinishReason::parse('unknown_reason'));
    }

    public function testParseReturnsNullForEmptyString(): void
    {
        $this->assertNull(FinishReason::parse(''));
    }
}
