<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Mcp;

use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Mcp\Transport\TransportInterface;
use Override;
use RuntimeException;

/**
 * MCP client implementing the Model Context Protocol.
 *
 * Transport-agnostic: delegates all communication to a TransportInterface
 * (e.g. HttpStreamable for HTTP, or a future Stdio transport).
 *
 * Implements ToolCollectionInterface: can be passed directly to Completion::withTools().
 * Tools are discovered lazily on first access via the MCP tools/list method.
 *
 * @see https://modelcontextprotocol.io/specification/2025-03-26/basic/lifecycle
 * @see https://modelcontextprotocol.io/specification/2025-03-26/server/tools
 */
class McpServer extends AbstractServer
{
    private array $serverCapabilities = [];
    private array $serverInfo = [];
    private int $jsonRpcId = 0;

    /**
     * @param TransportInterface $transport MCP transport layer
     * @param string $protocolVersion MCP protocol version to negotiate
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $protocolVersion = '2025-03-26',
    ) {
    }

    /**
     * Manually initialize the MCP session.
     *
     * Performs the full MCP lifecycle handshake:
     * 1. Send initialize request (capability negotiation)
     * 2. Send initialized notification
     * 3. Send tools/list (tool discovery with pagination)
     *
     * If already initialized, this is a no-op.
     */
    public function initialize(): void
    {
        $this->ensureInitialized();
    }

    /**
     * Gracefully shut down the MCP session.
     *
     * Delegates to the transport layer for connection cleanup.
     * Resets internal state so the server can be re-initialized.
     */
    public function shutdown(): void
    {
        try {
            $this->transport->close();
        } finally {
            $this->resetState();
        }
    }

    /**
     * Call a tool on the MCP server.
     *
     * @param string $name Tool name
     * @param array $arguments Tool arguments
     *
     * @return string Tool result as text
     * @throws RuntimeException On JSON-RPC error, tool execution error, or transport error
     */
    public function callTool(string $name, array $arguments): string
    {
        $this->ensureInitialized();

        $result = $this->sendJsonRpc('tools/call', [
            'name' => $name,
            'arguments' => (object)$arguments,
        ]);

        if (!empty($result['isError'])) {
            $errorText = $this->extractTextFromContent($result['content'] ?? []);

            throw new RuntimeException(
                sprintf('MCP tool "%s" execution error: %s', $name, $errorText ?: 'unknown error')
            );
        }

        return $this->extractTextFromContent($result['content'] ?? []);
    }

    #[Override]
    public function execute(ToolCall $toolCall): ToolResult
    {
        $result = $this->callTool($toolCall->name, $toolCall->arguments);

        return new ToolResult(
            toolCallId: $toolCall->id,
            content: $result,
        );
    }

    /**
     * Get the MCP session ID (delegated to the transport layer).
     */
    public function getSessionId(): ?string
    {
        return $this->transport->getSessionId();
    }

    /**
     * Get the MCP server capabilities (empty if not yet initialized).
     *
     * @return array
     */
    public function getServerCapabilities(): array
    {
        return $this->serverCapabilities;
    }

    /**
     * Get the MCP server info (empty if not yet initialized).
     *
     * @return array
     */
    public function getServerInfo(): array
    {
        return $this->serverInfo;
    }

    #[Override]
    protected function doInitialize(): void
    {
        $this->performInitialize();
        $this->sendInitializedNotification();
        $this->discoverTools();
    }

    /**
     * Step 1: Send initialize request and negotiate capabilities.
     */
    private function performInitialize(): void
    {
        $result = $this->sendJsonRpc('initialize', [
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => (object)[],
            'clientInfo' => [
                'name' => 'bycerfrance/llm-api-lib',
                'version' => '1.0.0',
            ],
        ]);

        $this->serverCapabilities = $result['capabilities'] ?? [];
        $this->serverInfo = $result['serverInfo'] ?? [];
    }

    /**
     * Step 2: Send initialized notification.
     */
    private function sendInitializedNotification(): void
    {
        $this->sendNotification('notifications/initialized');
    }

    /**
     * Step 3: Discover tools via tools/list with pagination support.
     */
    private function discoverTools(): void
    {
        $cursor = null;

        do {
            $params = null !== $cursor ? ['cursor' => $cursor] : null;
            $result = $this->sendJsonRpc('tools/list', $params);

            foreach ($result['tools'] ?? [] as $toolData) {
                $tool = new McpTool(
                    name: $toolData['name'],
                    description: $toolData['description'] ?? '',
                    parameters: $toolData['inputSchema'] ?? [],
                    server: $this,
                );
                $this->tools[$tool->getName()] = $tool;
            }

            $cursor = $result['nextCursor'] ?? null;
        } while (null !== $cursor);
    }

    /**
     * Send a JSON-RPC request (expects a response with result).
     *
     * @param string $method JSON-RPC method
     * @param array|null $params Method parameters
     *
     * @return array The "result" field from the JSON-RPC response
     * @throws RuntimeException On transport error or JSON-RPC error
     */
    private function sendJsonRpc(string $method, ?array $params = null): array
    {
        $body = [
            'jsonrpc' => '2.0',
            'id' => ++$this->jsonRpcId,
            'method' => $method,
        ];

        if (null !== $params) {
            $body['params'] = $params;
        }

        $responseBody = $this->transport->request(json_encode($body, JSON_THROW_ON_ERROR));
        $json = json_decode($responseBody, true, flags: JSON_THROW_ON_ERROR);

        if (isset($json['error'])) {
            throw new RuntimeException(
                sprintf(
                    'MCP JSON-RPC error %d: %s',
                    $json['error']['code'] ?? 0,
                    $json['error']['message'] ?? 'unknown error',
                )
            );
        }

        return $json['result'] ?? [];
    }

    /**
     * Send a JSON-RPC notification (no response expected).
     *
     * @param string $method JSON-RPC method
     * @param array|null $params Method parameters
     */
    private function sendNotification(string $method, ?array $params = null): void
    {
        $body = [
            'jsonrpc' => '2.0',
            'method' => $method,
        ];

        if (null !== $params) {
            $body['params'] = $params;
        }

        $this->transport->notify(json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * Extract text content from MCP tool result content array.
     *
     * @param array $content MCP content array [{type, text}, ...]
     *
     * @return string Concatenated text from all text content items
     */
    private function extractTextFromContent(array $content): string
    {
        $texts = [];

        foreach ($content as $item) {
            if (($item['type'] ?? null) === 'text') {
                $texts[] = $item['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }

    /**
     * Reset internal state for re-initialization.
     */
    private function resetState(): void
    {
        $this->serverCapabilities = [];
        $this->serverInfo = [];
        $this->tools = [];
        $this->jsonRpcId = 0;
        $this->resetInitialized();
    }
}
