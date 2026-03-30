<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Mcp;

use ByCerfrance\LlmApiLib\Completion\Tool\AbstractTool;
use ByCerfrance\LlmApiLib\Mcp\McpServer;
use ByCerfrance\LlmApiLib\Mcp\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(McpTool::class)]
#[UsesClass(AbstractTool::class)]
class McpToolTest extends TestCase
{
    public function testConstruction(): void
    {
        $server = $this->createMock(McpServer::class);

        $tool = new McpTool(
            name: 'get_weather',
            description: 'Get current weather for a location',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'location' => ['type' => 'string', 'description' => 'City name'],
                ],
                'required' => ['location'],
            ],
            server: $server,
        );

        $this->assertSame('get_weather', $tool->getName());
        $this->assertSame('Get current weather for a location', $tool->getDescription());
        $this->assertSame(
            [
                'type' => 'object',
                'properties' => [
                    'location' => ['type' => 'string', 'description' => 'City name'],
                ],
                'required' => ['location'],
            ],
            $tool->getParameters()
        );
    }

    public function testJsonSerialize(): void
    {
        $server = $this->createMock(McpServer::class);

        $tool = new McpTool(
            name: 'search',
            description: 'Search for information',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string'],
                ],
            ],
            server: $server,
        );

        $json = $tool->jsonSerialize();

        $this->assertSame('function', $json['type']);
        $this->assertSame('search', $json['function']['name']);
        $this->assertSame('Search for information', $json['function']['description']);
        $this->assertEquals(
            (object)['type' => 'object', 'properties' => ['query' => ['type' => 'string']]],
            $json['function']['parameters']
        );
    }

    public function testExecuteDelegatesToMcpServer(): void
    {
        $server = $this->createMock(McpServer::class);
        $server->expects($this->once())
            ->method('callTool')
            ->with('get_weather', ['location' => 'Paris'])
            ->willReturn('Temperature: 18°C, Conditions: Sunny');

        $tool = new McpTool(
            name: 'get_weather',
            description: 'Get weather',
            parameters: ['type' => 'object'],
            server: $server,
        );

        $result = $tool->execute(['location' => 'Paris']);

        $this->assertSame('Temperature: 18°C, Conditions: Sunny', $result);
    }
}
