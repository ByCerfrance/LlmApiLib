<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion\Message;

use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssistantMessage::class)]
class AssistantMessageTest extends TestCase
{
    public function testConstructionWithString(): void
    {
        $message = new AssistantMessage(content: 'Hello');

        $this->assertSame(RoleEnum::ASSISTANT, $message->getRole());
        $this->assertInstanceOf(TextContent::class, $message->getContent());
        $this->assertSame('Hello', (string)$message->getContent());
        $this->assertFalse($message->hasToolCalls());
        $this->assertEmpty($message->getToolCalls());
    }

    public function testConstructionWithNull(): void
    {
        $message = new AssistantMessage(content: null);

        $this->assertSame('', (string)$message->getContent());
    }

    public function testConstructionWithContentInterface(): void
    {
        $content = new TextContent('Custom');
        $message = new AssistantMessage(content: $content);

        $this->assertSame($content, $message->getContent());
    }

    public function testConstructionWithToolCalls(): void
    {
        $toolCalls = [
            new ToolCall('call_1', 'tool_a', ['arg' => 'value']),
            new ToolCall('call_2', 'tool_b', []),
        ];

        $message = new AssistantMessage(content: null, toolCalls: $toolCalls);

        $this->assertTrue($message->hasToolCalls());
        $this->assertCount(2, $message->getToolCalls());
    }

    public function testToolCallsFiltersInvalidItems(): void
    {
        $validToolCall = new ToolCall('call_1', 'tool', []);
        $message = new AssistantMessage(
            content: null,
            toolCalls: [$validToolCall, 'invalid', 123, null],
        );

        $this->assertCount(1, $message->getToolCalls());
    }

    public function testJsonSerializeWithoutToolCalls(): void
    {
        $message = new AssistantMessage(content: 'Response');

        $json = $message->jsonSerialize();

        $this->assertSame(RoleEnum::ASSISTANT, $json['role']);
        $this->assertInstanceOf(TextContent::class, $json['content']);
        $this->assertArrayNotHasKey('tool_calls', $json);
    }

    public function testJsonSerializeWithToolCalls(): void
    {
        $toolCalls = [
            new ToolCall('call_1', 'search', ['query' => 'test']),
        ];

        $message = new AssistantMessage(content: null, toolCalls: $toolCalls);

        $json = $message->jsonSerialize();

        $this->assertSame(RoleEnum::ASSISTANT, $json['role']);
        $this->assertNull($json['content']);
        $this->assertArrayHasKey('tool_calls', $json);
        $this->assertCount(1, $json['tool_calls']);
    }

    public function testRequiredCapabilities(): void
    {
        $message = new AssistantMessage(content: 'Test');

        $capabilities = $message->requiredCapabilities();
        $this->assertNotEmpty($capabilities);
    }
}
