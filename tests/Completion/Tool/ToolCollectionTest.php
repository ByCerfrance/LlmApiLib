<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion\Tool;

use ByCerfrance\LlmApiLib\Completion\Tool\Tool;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ToolCollection::class)]
class ToolCollectionTest extends TestCase
{
    private function createTool(string $name): Tool
    {
        return new Tool(
            name: $name,
            description: "Tool $name",
            parameters: ['type' => 'object'],
            callback: fn(array $args) => "Result from $name",
        );
    }

    public function testConstruction(): void
    {
        $tool1 = $this->createTool('tool_a');
        $tool2 = $this->createTool('tool_b');

        $collection = new ToolCollection($tool1, $tool2);

        $this->assertCount(2, $collection);
    }

    public function testGet(): void
    {
        $tool = $this->createTool('my_tool');
        $collection = new ToolCollection($tool);

        $this->assertSame($tool, $collection->get('my_tool'));
    }

    public function testGetThrowsOnNotFound(): void
    {
        $collection = new ToolCollection();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tool "unknown" not found');

        $collection->get('unknown');
    }

    public function testHas(): void
    {
        $tool = $this->createTool('existing');
        $collection = new ToolCollection($tool);

        $this->assertTrue($collection->has('existing'));
        $this->assertFalse($collection->has('not_existing'));
    }

    public function testExecute(): void
    {
        $tool = new Tool(
            name: 'calculator',
            description: 'Calculate',
            parameters: ['type' => 'object'],
            callback: fn(array $args) => $args['a'] * $args['b'],
        );

        $collection = new ToolCollection($tool);

        $toolCall = new ToolCall(
            id: 'call_123',
            name: 'calculator',
            arguments: ['a' => 6, 'b' => 7],
        );

        $result = $collection->execute($toolCall);

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertSame('call_123', $result->getToolCallId());
    }

    public function testIterable(): void
    {
        $tool1 = $this->createTool('first');
        $tool2 = $this->createTool('second');

        $collection = new ToolCollection($tool1, $tool2);

        $names = [];
        foreach ($collection as $tool) {
            $names[] = $tool->getName();
        }

        $this->assertContains('first', $names);
        $this->assertContains('second', $names);
    }

    public function testJsonSerialize(): void
    {
        $tool = $this->createTool('serializable');
        $collection = new ToolCollection($tool);

        $json = $collection->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertCount(1, $json);
    }

    public function testDuplicateToolsOverwrite(): void
    {
        $tool1 = new Tool(
            name: 'same_name',
            description: 'First',
            parameters: [],
            callback: fn() => 'first',
        );
        $tool2 = new Tool(
            name: 'same_name',
            description: 'Second',
            parameters: [],
            callback: fn() => 'second',
        );

        $collection = new ToolCollection($tool1, $tool2);

        $this->assertCount(1, $collection);
        $this->assertSame('Second', $collection->get('same_name')->getDescription());
    }
}
