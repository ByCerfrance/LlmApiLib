<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\OpenApi;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\OpenApi\OpenApi;
use ByCerfrance\LlmApiLib\OpenApi\OpenApiTool;
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
use cebe\openapi\spec\OpenApi as OpenApiSpec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * An OpenAPI specification used as the tool collection of a chat.
 */
#[CoversClass(OpenApi::class)]
#[UsesClass(Chat::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(Model::class)]
#[UsesClass(OpenApiTool::class)]
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
class OpenApiChatTest extends TestCase
{
    public function testChatExecutesOperationsThroughTheHttpClient(): void
    {
        $spec = new OpenApiSpec([
            'openapi' => '3.0.0', 'info' => ['title' => 'Test', 'version' => '1'],
            'servers' => [['url' => 'https://example.test']],
            'paths' => ['/calculate' => ['get' => ['operationId' => 'calculate', 'responses' => ['200' => ['description' => 'OK']]]]],
        ]);
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturnCallback(function (RequestInterface $request): ResponseInterface {
            $this->assertSame('https://example.test/calculate', (string) $request->getUri());

            return new Response('OpenAPI result');
        });
        $provider = new ScriptedChatProvider([
            ScriptedChatProvider::toolTurn(new ToolCall('one', 'calculate', [])),
            ScriptedChatProvider::answer(),
        ]);
        (new Chat($provider, new Model('model')))->chat(new ChatRequest(new Conversation(['Calculate']), tools: new OpenApi($spec, $client)));
        $messages = iterator_to_array($provider->requests[1]->getRequest()->getConversation(), false);
        $this->assertSame('OpenAPI result', (string) $messages[2]->getContent());
    }
}
