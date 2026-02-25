<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion\Tool;

use ByCerfrance\LlmApiLib\Completion\Content\JsonContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolResult::class)]
#[UsesClass(JsonContent::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(RoleEnum::class)]
class ToolResultTest extends TestCase
{
    public function testConstructionWithString(): void
    {
        $result = new ToolResult(
            toolCallId: 'call_123',
            content: 'The weather is sunny',
        );

        $this->assertSame('call_123', $result->getToolCallId());
        $this->assertSame(RoleEnum::TOOL, $result->getRole());
        $this->assertInstanceOf(TextContent::class, $result->getContent());
        $this->assertSame('The weather is sunny', (string)$result->getContent());
    }

    public function testConstructionWithArray(): void
    {
        $result = new ToolResult(
            toolCallId: 'call_456',
            content: ['temperature' => 20, 'unit' => 'celsius'],
        );

        $this->assertInstanceOf(JsonContent::class, $result->getContent());
    }

    public function testConstructionWithContentInterface(): void
    {
        $textContent = new TextContent('Custom content');
        $result = new ToolResult(
            toolCallId: 'call_789',
            content: $textContent,
        );

        $this->assertSame($textContent, $result->getContent());
    }

    public function testJsonSerialize(): void
    {
        $result = new ToolResult(
            toolCallId: 'call_abc',
            content: 'Result data',
        );

        $json = $result->jsonSerialize();

        $this->assertSame(RoleEnum::TOOL, $json['role']);
        $this->assertSame('call_abc', $json['tool_call_id']);
        $this->assertSame('Result data', $json['content']);
    }

    public function testRequiredCapabilities(): void
    {
        $result = new ToolResult(
            toolCallId: 'call_def',
            content: 'Some result',
        );

        $capabilities = $result->requiredCapabilities();
        $this->assertNotEmpty($capabilities);
    }
}
