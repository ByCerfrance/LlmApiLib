<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Payload\Builder;

use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\Choices;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\Builder\MessageBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Tests\Payload\Support\PayloadReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageBuilder::class)]
class MessageBuilderTest extends TestCase
{
    public function testSupportsMessageOnly(): void
    {
        $builder = new MessageBuilder();

        $this->assertTrue($builder->supports(new Message('hello'), new BuildContext()));
        $this->assertFalse($builder->supports(new \stdClass(), new BuildContext()));
    }

    public function testBuildSimpleMessage(): void
    {
        $builder = new MessageBuilder();
        $message = new Message('hello');

        $payload = $builder->build($message, new PayloadBuilder(), new BuildContext());

        $this->assertSame(PayloadReference::message($message), $payload);
    }

    public function testBuildAssistantMessageWithToolCalls(): void
    {
        $builder = new MessageBuilder();
        $message = new AssistantMessage(
            toolCalls: [
                new ToolCall('call-1', 'weather', ['city' => 'Paris']),
            ],
        );

        $payload = $builder->build($message, new PayloadBuilder(), new BuildContext());

        $this->assertSame(PayloadReference::message($message), $payload);
    }

    public function testBuildToolResultMessage(): void
    {
        $builder = new MessageBuilder();
        $message = new ToolResult(toolCallId: 'call-1', content: ['ok' => true]);

        $payload = $builder->build($message, new PayloadBuilder(), new BuildContext());

        $this->assertSame(PayloadReference::message($message), $payload);
    }

    public function testBuildChoicesMessageUsesPreferred(): void
    {
        $builder = new MessageBuilder();
        $choices = new Choices(
            new Message('first'),
            new Message('second'),
        );
        $choices->setPreferred(1);

        $payload = $builder->build($choices, new PayloadBuilder(), new BuildContext());

        $this->assertSame(PayloadReference::message($choices), $payload);
    }

    public function testBuildAssistantMessageWithContentAndToolCallsDropsContent(): void
    {
        $builder = new MessageBuilder();
        $message = new AssistantMessage(
            content: 'I will call a tool',
            toolCalls: [
                new ToolCall('call-1', 'weather', ['city' => 'Paris']),
            ],
        );

        $payload = $builder->build($message, new PayloadBuilder(), new BuildContext());

        $this->assertNull($payload['content']);
        $this->assertArrayHasKey('tool_calls', $payload);
        $this->assertSame('assistant', $payload['role']);
    }
}
