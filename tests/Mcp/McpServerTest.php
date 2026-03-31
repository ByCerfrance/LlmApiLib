<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Mcp;

use ByCerfrance\LlmApiLib\Completion\Tool\AbstractTool;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollectionInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Mcp\AbstractServer;
use ByCerfrance\LlmApiLib\Mcp\McpServer;
use ByCerfrance\LlmApiLib\Mcp\McpTool;
use ByCerfrance\LlmApiLib\Mcp\Transport\TransportInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(McpServer::class)]
#[CoversClass(AbstractServer::class)]
#[UsesClass(McpTool::class)]
#[UsesClass(AbstractTool::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolResult::class)]
class McpServerTest extends TestCase
{
    /**
     * Encode a JSON-RPC success response.
     */
    private function jsonRpcResult(array $result): string
    {
        return json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => $result]);
    }

    /**
     * Encode a JSON-RPC error response.
     */
    private function jsonRpcError(int $code, string $message): string
    {
        return json_encode(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['code' => $code, 'message' => $message]]);
    }

    /**
     * Build a mock TransportInterface that returns responses in sequence.
     *
     * @param string[] $requestResponses Responses for request() calls (in order)
     * @param int $notifyCount Expected number of notify() calls
     * @param string|null $sessionId Session ID to return from getSessionId()
     * @param string[]|null &$capturedRequests Array to capture request() bodies
     * @param string[]|null &$capturedNotifications Array to capture notify() bodies
     */
    private function createTransport(
        array $requestResponses = [],
        int $notifyCount = 0,
        ?string $sessionId = 'test-session-123',
        ?array &$capturedRequests = null,
        ?array &$capturedNotifications = null,
    ): TransportInterface {
        $capturedRequests = $capturedRequests ?? [];
        $capturedNotifications = $capturedNotifications ?? [];

        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($this->exactly(count($requestResponses)))
            ->method('request')
            ->willReturnCallback(function (string $body) use (&$requestResponses, &$capturedRequests): string {
                $capturedRequests[] = $body;
                return array_shift($requestResponses);
            });

        $transport->expects($this->exactly($notifyCount))
            ->method('notify')
            ->willReturnCallback(function (string $body) use (&$capturedNotifications): void {
                $capturedNotifications[] = $body;
            });

        $transport->method('getSessionId')->willReturn($sessionId);

        return $transport;
    }

    /**
     * Build standard init responses: initialize result + tools/list result.
     * (The initialized notification goes through notify(), not request().)
     *
     * @param array $tools Tools for tools/list response
     *
     * @return string[] Two JSON-RPC responses: [initialize, tools/list]
     */
    private function initResponses(array $tools = []): array
    {
        return [
            $this->jsonRpcResult([
                'protocolVersion' => '2025-03-26',
                'capabilities' => ['tools' => ['listChanged' => true]],
                'serverInfo' => ['name' => 'TestServer', 'version' => '1.0.0'],
            ]),
            $this->jsonRpcResult(['tools' => $tools]),
        ];
    }

    /**
     * Create a McpServer with standard init sequence.
     *
     * @param array $tools Tools to discover
     * @param string|null $sessionId Session ID
     * @param string[] $extraRequestResponses Additional responses after init
     * @param array|null &$capturedRequests Captured request() bodies
     * @param array|null &$capturedNotifications Captured notify() bodies
     */
    private function createInitializedServer(
        array $tools = [],
        ?string $sessionId = 'test-session-123',
        array $extraRequestResponses = [],
        ?array &$capturedRequests = null,
        ?array &$capturedNotifications = null,
    ): McpServer {
        $transport = $this->createTransport(
            requestResponses: [...$this->initResponses($tools), ...$extraRequestResponses],
            notifyCount: 1, // initialized notification
            sessionId: $sessionId,
            capturedRequests: $capturedRequests,
            capturedNotifications: $capturedNotifications,
        );

        return new McpServer(transport: $transport);
    }

    // -----------------------------------------------------------------------
    // Lifecycle tests
    // -----------------------------------------------------------------------

    public function testInitializePerformsFullHandshake(): void
    {
        $capturedRequests = [];
        $capturedNotifications = [];
        $server = $this->createInitializedServer(
            tools: [['name' => 'get_weather', 'description' => 'Get weather', 'inputSchema' => ['type' => 'object']]],
            capturedRequests: $capturedRequests,
            capturedNotifications: $capturedNotifications,
        );
        $server->initialize();

        // 2 request() calls: initialize + tools/list
        $this->assertCount(2, $capturedRequests);

        // Request 1: initialize
        $body1 = json_decode($capturedRequests[0], true);
        $this->assertSame('initialize', $body1['method']);
        $this->assertSame('2025-03-26', $body1['params']['protocolVersion']);
        $this->assertArrayHasKey('clientInfo', $body1['params']);
        $this->assertArrayHasKey('id', $body1);

        // 1 notify() call: initialized
        $this->assertCount(1, $capturedNotifications);
        $notif = json_decode($capturedNotifications[0], true);
        $this->assertSame('notifications/initialized', $notif['method']);
        $this->assertArrayNotHasKey('id', $notif);

        // Request 2: tools/list
        $body2 = json_decode($capturedRequests[1], true);
        $this->assertSame('tools/list', $body2['method']);
    }

    public function testInitializeIsIdempotent(): void
    {
        $server = $this->createInitializedServer();
        $server->initialize();
        $server->initialize(); // Second call: no-op (transport expects exact call counts)
    }

    public function testLazyInitializationOnGet(): void
    {
        $server = $this->createInitializedServer(
            tools: [['name' => 'my_tool', 'description' => 'A tool', 'inputSchema' => ['type' => 'object']]],
        );

        // No explicit initialize() — should auto-init on get()
        $tool = $server->get('my_tool');

        $this->assertInstanceOf(McpTool::class, $tool);
        $this->assertSame('my_tool', $tool->getName());
    }

    public function testLazyInitializationOnCount(): void
    {
        $server = $this->createInitializedServer(tools: [
            ['name' => 'tool_a', 'description' => 'A', 'inputSchema' => []],
            ['name' => 'tool_b', 'description' => 'B', 'inputSchema' => []],
        ]);

        $this->assertSame(2, $server->count());
    }

    public function testLazyInitializationOnHas(): void
    {
        $server = $this->createInitializedServer(tools: [
            ['name' => 'existing_tool', 'description' => 'Exists', 'inputSchema' => []],
        ]);

        $this->assertTrue($server->has('existing_tool'));
        $this->assertFalse($server->has('nonexistent'));
    }

    public function testLazyInitializationOnIterator(): void
    {
        $server = $this->createInitializedServer(tools: [
            ['name' => 'tool_a', 'description' => 'A', 'inputSchema' => []],
            ['name' => 'tool_b', 'description' => 'B', 'inputSchema' => []],
        ]);

        $tools = iterator_to_array($server->getIterator());

        $this->assertCount(2, $tools);
        $this->assertSame('tool_a', $tools[0]->getName());
        $this->assertSame('tool_b', $tools[1]->getName());
    }

    public function testLazyInitializationOnJsonSerialize(): void
    {
        $server = $this->createInitializedServer(tools: [
            ['name' => 'tool_a', 'description' => 'A', 'inputSchema' => ['type' => 'object']],
        ]);

        $serialized = $server->jsonSerialize();

        $this->assertCount(1, $serialized);
        $this->assertInstanceOf(McpTool::class, $serialized[0]);
    }

    // -----------------------------------------------------------------------
    // Session management tests
    // -----------------------------------------------------------------------

    public function testSessionIdDelegatedToTransport(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('getSessionId')->willReturn('my-session-abc');

        $server = new McpServer(transport: $transport);
        $this->assertSame('my-session-abc', $server->getSessionId());
    }

    public function testNoSessionIdWhenTransportReturnsNull(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('getSessionId')->willReturn(null);

        $server = new McpServer(transport: $transport);
        $this->assertNull($server->getSessionId());
    }

    // -----------------------------------------------------------------------
    // Server capabilities and info
    // -----------------------------------------------------------------------

    public function testServerCapabilitiesAndInfo(): void
    {
        $server = $this->createInitializedServer();
        $server->initialize();

        $this->assertSame(['tools' => ['listChanged' => true]], $server->getServerCapabilities());
        $this->assertSame(['name' => 'TestServer', 'version' => '1.0.0'], $server->getServerInfo());
    }

    // -----------------------------------------------------------------------
    // Tool discovery tests
    // -----------------------------------------------------------------------

    public function testDiscoverToolsFromToolsList(): void
    {
        $server = $this->createInitializedServer(tools: [
            [
                'name' => 'get_weather',
                'description' => 'Get current weather for a location',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['location' => ['type' => 'string']],
                    'required' => ['location'],
                ],
            ],
            [
                'name' => 'search',
                'description' => 'Search the web',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['query' => ['type' => 'string']],
                ],
            ],
        ]);
        $server->initialize();

        $this->assertSame(2, $server->count());
        $this->assertTrue($server->has('get_weather'));
        $this->assertTrue($server->has('search'));

        $weather = $server->get('get_weather');
        $this->assertInstanceOf(McpTool::class, $weather);
        $this->assertSame('get_weather', $weather->getName());
        $this->assertSame('Get current weather for a location', $weather->getDescription());
        $this->assertSame(
            ['type' => 'object', 'properties' => ['location' => ['type' => 'string']], 'required' => ['location']],
            $weather->getParameters()
        );
    }

    public function testDiscoverToolsWithPagination(): void
    {
        $capturedRequests = [];
        $transport = $this->createTransport(
            requestResponses: [
                // initialize
                $this->jsonRpcResult([
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => ['tools' => []],
                    'serverInfo' => ['name' => 'Test', 'version' => '1.0.0'],
                ]),
                // tools/list page 1 (with nextCursor)
                $this->jsonRpcResult([
                    'tools' => [['name' => 'tool_a', 'description' => 'A', 'inputSchema' => []]],
                    'nextCursor' => 'cursor-page-2',
                ]),
                // tools/list page 2 (no nextCursor)
                $this->jsonRpcResult([
                    'tools' => [['name' => 'tool_b', 'description' => 'B', 'inputSchema' => []]],
                ]),
            ],
            notifyCount: 1,
            capturedRequests: $capturedRequests,
        );

        $server = new McpServer(transport: $transport);
        $server->initialize();

        $this->assertSame(2, $server->count());
        $this->assertTrue($server->has('tool_a'));
        $this->assertTrue($server->has('tool_b'));

        // Verify page 2 request includes cursor
        $body3 = json_decode($capturedRequests[2], true);
        $this->assertSame('tools/list', $body3['method']);
        $this->assertSame('cursor-page-2', $body3['params']['cursor']);
    }

    public function testDiscoverToolsWithMissingDescription(): void
    {
        $server = $this->createInitializedServer(tools: [
            ['name' => 'minimal_tool', 'inputSchema' => ['type' => 'object']],
        ]);
        $server->initialize();

        $tool = $server->get('minimal_tool');
        $this->assertSame('', $tool->getDescription());
    }

    public function testGetNonExistentToolThrowsException(): void
    {
        $server = $this->createInitializedServer(tools: [
            ['name' => 'existing', 'description' => 'Exists', 'inputSchema' => []],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tool "nonexistent" not found in collection');
        $server->get('nonexistent');
    }

    // -----------------------------------------------------------------------
    // Tool execution tests
    // -----------------------------------------------------------------------

    public function testCallToolSuccess(): void
    {
        $server = $this->createInitializedServer(
            tools: [['name' => 'get_weather', 'description' => 'Get weather', 'inputSchema' => ['type' => 'object']]],
            extraRequestResponses: [
                $this->jsonRpcResult([
                    'content' => [['type' => 'text', 'text' => 'Temperature: 22°C, Sunny']],
                    'isError' => false,
                ]),
            ],
        );
        $server->initialize();

        $result = $server->callTool('get_weather', ['location' => 'Paris']);
        $this->assertSame('Temperature: 22°C, Sunny', $result);
    }

    public function testCallToolWithMultipleTextContent(): void
    {
        $server = $this->createInitializedServer(
            tools: [['name' => 'report', 'description' => 'Generate report', 'inputSchema' => []]],
            extraRequestResponses: [
                $this->jsonRpcResult([
                    'content' => [
                        ['type' => 'text', 'text' => 'Line 1'],
                        ['type' => 'text', 'text' => 'Line 2'],
                        ['type' => 'text', 'text' => 'Line 3'],
                    ],
                    'isError' => false,
                ]),
            ],
        );
        $server->initialize();

        $result = $server->callTool('report', []);
        $this->assertSame("Line 1\nLine 2\nLine 3", $result);
    }

    public function testCallToolWithNonTextContentIgnored(): void
    {
        $server = $this->createInitializedServer(
            tools: [['name' => 'mixed', 'description' => 'Mixed content', 'inputSchema' => []]],
            extraRequestResponses: [
                $this->jsonRpcResult([
                    'content' => [
                        ['type' => 'text', 'text' => 'Some text'],
                        ['type' => 'image', 'data' => 'base64...', 'mimeType' => 'image/png'],
                        ['type' => 'text', 'text' => 'More text'],
                    ],
                    'isError' => false,
                ]),
            ],
        );
        $server->initialize();

        $result = $server->callTool('mixed', []);
        $this->assertSame("Some text\nMore text", $result);
    }

    public function testCallToolExecutionError(): void
    {
        $server = $this->createInitializedServer(
            tools: [['name' => 'failing', 'description' => 'Fails', 'inputSchema' => []]],
            extraRequestResponses: [
                $this->jsonRpcResult([
                    'content' => [['type' => 'text', 'text' => 'API rate limit exceeded']],
                    'isError' => true,
                ]),
            ],
        );
        $server->initialize();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MCP tool "failing" execution error: API rate limit exceeded');
        $server->callTool('failing', []);
    }

    public function testCallToolSendsCorrectJsonRpc(): void
    {
        $capturedRequests = [];
        $server = $this->createInitializedServer(
            tools: [['name' => 'my_tool', 'description' => 'Tool', 'inputSchema' => ['type' => 'object']]],
            extraRequestResponses: [
                $this->jsonRpcResult([
                    'content' => [['type' => 'text', 'text' => 'ok']],
                    'isError' => false,
                ]),
            ],
            capturedRequests: $capturedRequests,
        );
        $server->initialize();
        $server->callTool('my_tool', ['key' => 'value', 'num' => 42]);

        // Request 3 (index 2) is the tools/call (after initialize + tools/list)
        $body = json_decode($capturedRequests[2], true);
        $this->assertSame('tools/call', $body['method']);
        $this->assertSame('my_tool', $body['params']['name']);
        $this->assertSame(['key' => 'value', 'num' => 42], (array)$body['params']['arguments']);
    }

    // -----------------------------------------------------------------------
    // execute() (ToolCollectionInterface) tests
    // -----------------------------------------------------------------------

    public function testExecuteReturnsToolResult(): void
    {
        $server = $this->createInitializedServer(
            tools: [['name' => 'calculator', 'description' => 'Calculate', 'inputSchema' => ['type' => 'object']]],
            extraRequestResponses: [
                $this->jsonRpcResult([
                    'content' => [['type' => 'text', 'text' => '42']],
                    'isError' => false,
                ]),
            ],
        );
        $server->initialize();

        $toolCall = new ToolCall(id: 'call_123', name: 'calculator', arguments: ['expr' => '6*7']);
        $result = $server->execute($toolCall);

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertSame('call_123', $result->getToolCallId());
        $this->assertSame('42', (string)$result->getContent());
    }

    // -----------------------------------------------------------------------
    // Error handling tests
    // -----------------------------------------------------------------------

    public function testJsonRpcErrorThrowsException(): void
    {
        $transport = $this->createTransport(
            requestResponses: [$this->jsonRpcError(-32602, 'Unsupported protocol version')],
            sessionId: null,
        );

        $server = new McpServer(transport: $transport);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MCP JSON-RPC error -32602: Unsupported protocol version');
        $server->initialize();
    }

    public function testTransportErrorPropagates(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('request')
            ->willThrowException(new RuntimeException('MCP HTTP error (500 Internal Server Error)'));

        $server = new McpServer(transport: $transport);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MCP HTTP error (500 Internal Server Error)');
        $server->initialize();
    }

    // -----------------------------------------------------------------------
    // Shutdown tests
    // -----------------------------------------------------------------------

    public function testShutdownDelegatesToTransportClose(): void
    {
        $transport = $this->createTransport(
            requestResponses: $this->initResponses(),
            notifyCount: 1,
        );
        $transport->expects($this->once())->method('close');

        $server = new McpServer(transport: $transport);
        $server->initialize();
        $server->shutdown();
    }

    public function testShutdownResetsStateForReinitialization(): void
    {
        // We need two separate transport mocks for two lifecycles
        // Instead, use a single transport that handles both init cycles
        $requestResponses = [
            // First init: initialize + tools/list
            ...$this->initResponses([['name' => 'tool_v1', 'description' => 'V1', 'inputSchema' => []]]),
            // Second init: initialize + tools/list (different tool)
            ...$this->initResponses([['name' => 'tool_v2', 'description' => 'V2', 'inputSchema' => []]]),
        ];

        $transport = $this->createTransport(
            requestResponses: $requestResponses,
            notifyCount: 2, // Two initialized notifications
        );

        $server = new McpServer(transport: $transport);

        // First lifecycle
        $server->initialize();
        $this->assertTrue($server->has('tool_v1'));

        $server->shutdown();

        // Second lifecycle (re-initialize)
        $server->initialize();
        $this->assertTrue($server->has('tool_v2'));
        $this->assertFalse($server->has('tool_v1'));
    }

    // -----------------------------------------------------------------------
    // ToolCollectionInterface compliance
    // -----------------------------------------------------------------------

    public function testImplementsToolCollectionInterface(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $server = new McpServer(transport: $transport);

        $this->assertInstanceOf(ToolCollectionInterface::class, $server);
    }
}
