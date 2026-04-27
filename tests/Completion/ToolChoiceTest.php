<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion;

use ByCerfrance\LlmApiLib\Completion\ToolChoice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolChoice::class)]
class ToolChoiceTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('auto', ToolChoice::AUTO->value);
        $this->assertSame('none', ToolChoice::NONE->value);
        $this->assertSame('required', ToolChoice::REQUIRED->value);
    }

    public function testFromString(): void
    {
        $this->assertSame(ToolChoice::AUTO, ToolChoice::from('auto'));
        $this->assertSame(ToolChoice::NONE, ToolChoice::from('none'));
        $this->assertSame(ToolChoice::REQUIRED, ToolChoice::from('required'));
    }

    public function testTryFromReturnsNullForUnknown(): void
    {
        $this->assertNull(ToolChoice::tryFrom('unknown'));
        $this->assertNull(ToolChoice::tryFrom(''));
    }

    public function testTryFromReturnsEnumForKnown(): void
    {
        $this->assertSame(ToolChoice::AUTO, ToolChoice::tryFrom('auto'));
        $this->assertSame(ToolChoice::REQUIRED, ToolChoice::tryFrom('required'));
    }

    public function testCaseCount(): void
    {
        $this->assertCount(3, ToolChoice::cases());
    }
}
