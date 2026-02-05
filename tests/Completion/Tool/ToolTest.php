<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion\Tool;

use ByCerfrance\LlmApiLib\Completion\Tool\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tool::class)]
class ToolTest extends TestCase
{
    public function testConstruction(): void
    {
        $tool = new Tool(
            name: 'get_weather',
            description: 'Get the current weather',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'location' => ['type' => 'string'],
                ],
                'required' => ['location'],
            ],
            callback: fn(array $args) => ['temperature' => 20, 'unit' => 'celsius'],
        );

        $this->assertSame('get_weather', $tool->getName());
        $this->assertSame('Get the current weather', $tool->getDescription());
        $this->assertSame(['type' => 'object', 'properties' => ['location' => ['type' => 'string']], 'required' => ['location']], $tool->getParameters());
    }

    public function testExecute(): void
    {
        $tool = new Tool(
            name: 'add',
            description: 'Add two numbers',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'a' => ['type' => 'number'],
                    'b' => ['type' => 'number'],
                ],
            ],
            callback: fn(array $args) => $args['a'] + $args['b'],
        );

        $result = $tool->execute(['a' => 5, 'b' => 3]);
        $this->assertSame(8, $result);
    }

    public function testJsonSerialize(): void
    {
        $tool = new Tool(
            name: 'search',
            description: 'Search for information',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string'],
                ],
            ],
            callback: fn(array $args) => 'results',
        );

        $json = $tool->jsonSerialize();

        $this->assertSame('function', $json['type']);
        $this->assertSame('search', $json['function']['name']);
        $this->assertSame('Search for information', $json['function']['description']);
        $this->assertEquals((object) ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]], $json['function']['parameters']);
    }
}
