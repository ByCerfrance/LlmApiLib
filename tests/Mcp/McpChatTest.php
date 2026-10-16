<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Mcp;

use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Mcp\McpServer;
use ByCerfrance\LlmApiLib\Mcp\McpTool;
use ByCerfrance\LlmApiLib\Mcp\Transport\TransportInterface;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Tests\Fixture\ScriptedChatProvider;
use ByCerfrance\LlmApiLib\Tool\AbstractTool;
use ByCerfrance\LlmApiLib\Tool\InvocationRegistry;
use ByCerfrance\LlmApiLib\Tool\JsonSchemaArgumentsValidator;
use ByCerfrance\LlmApiLib\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Tool\ToolSchema;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * An MCP server used as the tool collection of a chat.
 */
#[CoversClass(McpServer::class)]
#[UsesClass(Chat::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(McpTool::class)]
#[UsesClass(Model::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(AbstractTool::class)]
#[UsesClass(InvocationRegistry::class)]
#[UsesClass(JsonSchemaArgumentsValidator::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolExecutor::class)]
#[UsesClass(ToolResult::class)]
#[UsesClass(ToolSchema::class)]
#[UsesClass(TokenUsage::class)]
class McpChatTest extends TestCase
{
    public function testChatExecutesToolsThroughTheServerTransport(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('notify');
        $transport->expects($this->exactly(3))->method('request')->willReturnCallback(
            static function (string $body): string {
                $request = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
                $result = match ($request['method']) {
                    'initialize' => ['protocolVersion' => '2025-03-26', 'capabilities' => []],
                    'tools/list' => ['tools' => [['name' => 'calculate', 'inputSchema' => ['type' => 'object', 'properties' => new stdClass()]]]],
                    'tools/call' => ['content' => [['type' => 'text', 'text' => 'MCP result']]],
                };

                return json_encode(['jsonrpc' => '2.0', 'id' => $request['id'], 'result' => $result], JSON_THROW_ON_ERROR);
            },
        );
        $provider = new ScriptedChatProvider([
            ScriptedChatProvider::toolTurn(new ToolCall('one', 'calculate', [])),
            ScriptedChatProvider::answer(),
        ]);
        (new Chat($provider, new Model('model')))->chat(new ChatRequest(new Conversation(['Calculate']), tools: new McpServer($transport)));
        $messages = iterator_to_array($provider->requests[1]->getRequest()->getConversation(), false);
        $this->assertSame('MCP result', (string) $messages[2]->getContent());
    }
}
