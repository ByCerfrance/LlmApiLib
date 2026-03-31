<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion\Message;

use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\FinishReason;
use ByCerfrance\LlmApiLib\Completion\Message\Choice;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Choice::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(FinishReason::class)]
class ChoiceTest extends TestCase
{
    public function testConstructionWithDefaults(): void
    {
        $message = new Message('hello');
        $choice = new Choice(message: $message);

        $this->assertSame($message, $choice->message);
        $this->assertNull($choice->finishReason);
        $this->assertSame(0, $choice->index);
    }

    public function testConstructionWithAllParameters(): void
    {
        $message = new Message('hello', RoleEnum::ASSISTANT);
        $choice = new Choice(
            message: $message,
            finishReason: FinishReason::STOP,
            index: 2,
        );

        $this->assertSame($message, $choice->message);
        $this->assertSame(FinishReason::STOP, $choice->finishReason);
        $this->assertSame(2, $choice->index);
    }

    public function testConstructionWithLengthFinishReason(): void
    {
        $message = new Message('truncated content');
        $choice = new Choice(
            message: $message,
            finishReason: FinishReason::LENGTH,
        );

        $this->assertSame(FinishReason::LENGTH, $choice->finishReason);
    }

    public function testConstructionWithToolCallsFinishReason(): void
    {
        $message = new Message('', RoleEnum::ASSISTANT);
        $choice = new Choice(
            message: $message,
            finishReason: FinishReason::TOOL_CALLS,
            index: 0,
        );

        $this->assertSame(FinishReason::TOOL_CALLS, $choice->finishReason);
    }
}
