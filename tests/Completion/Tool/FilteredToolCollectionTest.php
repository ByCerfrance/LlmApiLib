<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion\Tool;

use ByCerfrance\LlmApiLib\Completion\Tool\FilteredToolCollection;
use ByCerfrance\LlmApiLib\Completion\Tool\Tool;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollectionInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(FilteredToolCollection::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ToolCollection::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolResult::class)]
class FilteredToolCollectionTest extends TestCase
{
    private function createCollection(): ToolCollection
    {
        return new ToolCollection(
            new Tool('tool_a', 'Tool A', ['type' => 'object'], fn() => 'a'),
            new Tool('tool_b', 'Tool B', ['type' => 'object'], fn() => 'b'),
            new Tool('tool_c', 'Tool C', ['type' => 'object'], fn() => 'c'),
        );
    }

    // -----------------------------------------------------------------------
    // Include filter
    // -----------------------------------------------------------------------

    public function testIncludeFilter(): void
    {
        $filtered = new FilteredToolCollection($this->createCollection(), ['tool_a', 'tool_b']);

        $this->assertTrue($filtered->has('tool_a'));
        $this->assertTrue($filtered->has('tool_b'));
        $this->assertFalse($filtered->has('tool_c'));
        $this->assertSame(2, $filtered->count());
    }

    public function testIncludeSingleTool(): void
    {
        $filtered = new FilteredToolCollection($this->createCollection(), ['tool_b']);

        $this->assertFalse($filtered->has('tool_a'));
        $this->assertTrue($filtered->has('tool_b'));
        $this->assertFalse($filtered->has('tool_c'));
        $this->assertSame(1, $filtered->count());
    }

    // -----------------------------------------------------------------------
    // Exclude filter
    // -----------------------------------------------------------------------

    public function testExcludeFilter(): void
    {
        $filtered = new FilteredToolCollection($this->createCollection(), ['!tool_c']);

        $this->assertTrue($filtered->has('tool_a'));
        $this->assertTrue($filtered->has('tool_b'));
        $this->assertFalse($filtered->has('tool_c'));
        $this->assertSame(2, $filtered->count());
    }

    public function testExcludeMultiple(): void
    {
        $filtered = new FilteredToolCollection($this->createCollection(), ['!tool_a', '!tool_c']);

        $this->assertFalse($filtered->has('tool_a'));
        $this->assertTrue($filtered->has('tool_b'));
        $this->assertFalse($filtered->has('tool_c'));
        $this->assertSame(1, $filtered->count());
    }

    // -----------------------------------------------------------------------
    // Include takes priority over exclude
    // -----------------------------------------------------------------------

    public function testIncludeTakesPriorityOverExclude(): void
    {
        $filtered = new FilteredToolCollection($this->createCollection(), ['tool_a', '!tool_c']);

        $this->assertTrue($filtered->has('tool_a'));
        $this->assertFalse($filtered->has('tool_b'));
        $this->assertFalse($filtered->has('tool_c'));
        $this->assertSame(1, $filtered->count());
    }

    // -----------------------------------------------------------------------
    // get() with filtered tools
    // -----------------------------------------------------------------------

    public function testGetAllowedTool(): void
    {
        $filtered = new FilteredToolCollection($this->createCollection(), ['tool_a']);

        $tool = $filtered->get('tool_a');
        $this->assertSame('tool_a', $tool->getName());
    }

    public function testGetFilteredToolThrowsException(): void
    {
        $filtered = new FilteredToolCollection($this->createCollection(), ['tool_a']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tool "tool_b" not found in collection');
        $filtered->get('tool_b');
    }

    // -----------------------------------------------------------------------
    // execute() with filtered tools
    // -----------------------------------------------------------------------

    public function testExecuteAllowedTool(): void
    {
        $filtered = new FilteredToolCollection($this->createCollection(), ['tool_a']);

        $toolCall = new ToolCall(id: 'call_1', name: 'tool_a', arguments: []);
        $result = $filtered->execute($toolCall);

        $this->assertInstanceOf(ToolResult::class, $result);
    }

    public function testExecuteFilteredToolThrowsException(): void
    {
        $filtered = new FilteredToolCollection($this->createCollection(), ['tool_a']);

        $toolCall = new ToolCall(id: 'call_1', name: 'tool_b', arguments: []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tool "tool_b" is not allowed by filter');
        $filtered->execute($toolCall);
    }

    // -----------------------------------------------------------------------
    // getIterator() / jsonSerialize()
    // -----------------------------------------------------------------------

    public function testIteratorRespectsFilter(): void
    {
        $filtered = new FilteredToolCollection($this->createCollection(), ['!tool_b']);

        $names = array_map(
            fn($tool) => $tool->getName(),
            iterator_to_array($filtered->getIterator(), false),
        );

        $this->assertSame(['tool_a', 'tool_c'], $names);
    }

    public function testJsonSerializeRespectsFilter(): void
    {
        $filtered = new FilteredToolCollection($this->createCollection(), ['tool_a', 'tool_c']);

        $serialized = $filtered->jsonSerialize();

        $this->assertCount(2, $serialized);
        $this->assertSame('tool_a', $serialized[0]->getName());
        $this->assertSame('tool_c', $serialized[1]->getName());
    }

    // -----------------------------------------------------------------------
    // ToolCollectionInterface compliance
    // -----------------------------------------------------------------------

    public function testImplementsToolCollectionInterface(): void
    {
        $filtered = new FilteredToolCollection($this->createCollection(), []);

        $this->assertInstanceOf(ToolCollectionInterface::class, $filtered);
    }

    public function testEmptyFilterAllowsAll(): void
    {
        $filtered = new FilteredToolCollection($this->createCollection(), []);

        $this->assertSame(3, $filtered->count());
        $this->assertTrue($filtered->has('tool_a'));
        $this->assertTrue($filtered->has('tool_b'));
        $this->assertTrue($filtered->has('tool_c'));
    }
}
