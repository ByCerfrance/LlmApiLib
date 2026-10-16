<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Chat;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatInterface;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Completion\Content\JsonContent;
use ByCerfrance\LlmApiLib\Completion\Content\NullContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\FinishReason;
use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\Message\UserMessage;
use ByCerfrance\LlmApiLib\Completion\Tool\AbstractTool;
use ByCerfrance\LlmApiLib\Completion\Tool\ChatTool;
use ByCerfrance\LlmApiLib\Completion\Tool\InvocationRegistry;
use ByCerfrance\LlmApiLib\Completion\Tool\JsonSchemaArgumentsValidator;
use ByCerfrance\LlmApiLib\Completion\Tool\Tool;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolErrorPolicy;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolSchema;
use ByCerfrance\LlmApiLib\Completion\ToolChoice;
use ByCerfrance\LlmApiLib\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Mcp\AbstractServer;
use ByCerfrance\LlmApiLib\Mcp\McpServer;
use ByCerfrance\LlmApiLib\Mcp\McpTool;
use ByCerfrance\LlmApiLib\Mcp\OpenApi;
use ByCerfrance\LlmApiLib\Mcp\OpenApiTool;
use ByCerfrance\LlmApiLib\Mcp\Transport\TransportInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Model\ParameterPolicy;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\Builder\ChatContentBuilder;
use ByCerfrance\LlmApiLib\Payload\Builder\ModelChatBuilder;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\OpenAICompatible;
use ByCerfrance\LlmApiLib\Provider\OpenAi;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use cebe\openapi\spec\OpenApi as OpenApiSpec;
use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

#[CoversClass(Chat::class)]
#[CoversClass(ToolExecutor::class)]
#[CoversClass(InvocationRegistry::class)]
#[CoversClass(JsonSchemaArgumentsValidator::class)]
#[CoversClass(ChatTool::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(Model::class)]
#[UsesClass(Capability::class)]
#[UsesClass(ParameterPolicy::class)]
#[UsesClass(OpenAICompatible::class)]
#[UsesClass(OpenAi::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(TokenUsage::class)]
#[UsesClass(PayloadBuilder::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(ChatPayload::class)]
#[UsesClass(ChatContentBuilder::class)]
#[UsesClass(ModelChatBuilder::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(FinishReason::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(NullContent::class)]
#[UsesClass(JsonContent::class)]
#[UsesClass(AbstractTool::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolCollection::class)]
#[UsesClass(ToolResult::class)]
#[UsesClass(ToolSchema::class)]
#[UsesClass(ToolErrorPolicy::class)]
#[UsesClass(ToolChoice::class)]
#[UsesClass(AbstractServer::class)]
#[UsesClass(McpServer::class)]
#[UsesClass(McpTool::class)]
#[UsesClass(OpenApi::class)]
#[UsesClass(OpenApiTool::class)]
class ToolsChatTest extends TestCase
{
    public function testRepeatedInvocationIsCachedButNewIdExecutesAgain(): void
    {
        $executions = 0;
        $tool = new Tool('calculate', 'Calculate', ['type' => 'object', 'required' => ['n'], 'properties' => ['n' => ['type' => 'integer']]],
            function (array $args) use (&$executions): string { $executions++; return (string) ($args['n'] * 2); });
        $requests = [];
        $chat = $this->chat([
            $this->toolTurn('one', '{"n":2}'), $this->toolTurn('one', '{"n":2}'),
            $this->toolTurn('two', '{"n":2}'), $this->finalTurn(),
        ], $requests);
        $request = new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), maxToolIterations: 3, toolChoice: ToolChoice::REQUIRED);
        $response = $chat->chat($request);
        $this->assertSame(2, $executions);
        $this->assertCount(4, $response->getTurns());
        $this->assertSame(12, $response->getUsage()->getTotalTokens());
        $this->assertNull($response->getUsage()->getCachedTokens());
        $this->assertSame(3, $response->getResult()->getUsage()->getTotalTokens());
        $this->assertCount(1, $request->getConversation());
        $this->assertCount(8, $response->toConversation());
        $this->assertSame('required', $requests[0]['tool_choice']);
        $this->assertSame('auto', $requests[1]['tool_choice']);
        $this->assertSame('Calculating', $requests[1]['messages'][1]['content']);
        $this->assertSame('signature', $requests[1]['messages'][1]['tool_calls'][0]['extra_content']['google']['thought_signature']);
        $this->assertSame('one', $requests[1]['messages'][2]['tool_call_id']);
        $this->assertSame('4', $requests[1]['messages'][2]['content']);
    }

    public function testCompletedHistoryCanBeForkedWithoutReplayingTools(): void
    {
        $executions = 0;
        $tool = new Tool('calculate', 'Calculate', ['type' => 'object'], function () use (&$executions): string { $executions++; return 'done'; });
        $requests = [];
        $chat = $this->chat([$this->toolTurn('one'), $this->finalTurn(), $this->toolTurn('one'), $this->finalTurn(), $this->finalTurn()], $requests);
        $request = new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool));
        $first = $chat->chat($request);
        $chat->chat($request->withConversation($first->toConversation()->withNewMessage('Branch A')));
        $chat->chat($request->withConversation($first->toConversation()->withNewMessage('Branch B')));
        $this->assertSame(1, $executions);
        $this->assertCount(4, $first->toConversation());
    }

    public function testInvalidArgumentsAreReportedBeforeCorrectedCallExecutes(): void
    {
        $executions = 0;
        $tool = new Tool('calculate', 'Calculate', ['type' => 'object', 'required' => ['n'], 'properties' => ['n' => ['type' => 'integer']]],
            function () use (&$executions): string { $executions++; return 'done'; });
        $requests = [];
        $chat = $this->chat([$this->toolTurn('bad', '{"n":"not a number"}'), $this->toolTurn('good', '{"n":2}'), $this->finalTurn()], $requests);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), toolErrorPolicy: ToolErrorPolicy::RETURN_ERROR));
        $this->assertSame(1, $executions);
        $error = json_decode($requests[1]['messages'][2]['content'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('invalid_arguments', $error['error']['type']);
    }

    public function testNestedEmptyObjectIsNotConfusedWithAnArray(): void
    {
        $executions = 0;
        $tool = new Tool('calculate', 'Calculate', ['type' => 'object', 'properties' => ['options' => ['type' => 'object', 'properties' => []]], 'required' => ['options']],
            function () use (&$executions): string { $executions++; return 'done'; });
        $requests = [];
        $chat = $this->chat([$this->toolTurn('bad', '{"options":[]}'), $this->toolTurn('good', '{"options":{}}'), $this->finalTurn()], $requests);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), toolErrorPolicy: ToolErrorPolicy::RETURN_ERROR));
        $this->assertSame(1, $executions);
        $this->assertSame('{"options":{}}', $requests[2]['messages'][3]['tool_calls'][0]['function']['arguments']);
    }

    public function testUnknownToolCanReturnAnErrorWithoutExecutingAnotherTool(): void
    {
        $requests = [];
        $chat = $this->chat([$this->toolTurn('missing'), $this->finalTurn()], $requests);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection(), toolErrorPolicy: ToolErrorPolicy::RETURN_ERROR));
        $error = json_decode($requests[1]['messages'][2]['content'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('unknown_tool', $error['error']['type']);
    }

    public function testToolFailureIsRecordedAndNotRepeated(): void
    {
        $executions = 0;
        $tool = new Tool('calculate', 'Calculate', [], function () use (&$executions): never { $executions++; throw new RuntimeException('private details'); });
        $requests = [];
        $chat = $this->chat([$this->toolTurn('one'), $this->toolTurn('one'), $this->finalTurn()], $requests);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), toolErrorPolicy: ToolErrorPolicy::RETURN_ERROR));
        $this->assertSame(1, $executions);
        $this->assertStringNotContainsString('private', $requests[1]['messages'][2]['content']);
        $this->assertSame('execution_failed', json_decode($requests[1]['messages'][2]['content'], true)['error']['type']);
    }

    public function testStopPolicyStopsAfterValidationFailure(): void
    {
        $tool = new Tool('calculate', 'Calculate', ['type' => 'object', 'required' => ['n']], fn(): never => $this->fail('Invalid arguments must not execute.'));
        $requests = [];
        $chat = $this->chat([$this->toolTurn('one')], $requests);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid_arguments');
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool)));
    }

    public function testIterationLimitPreventsTheNextToolRound(): void
    {
        $executions = 0;
        $tool = new Tool('calculate', 'Calculate', [], function () use (&$executions): string { $executions++; return 'done'; });
        $requests = [];
        $chat = $this->chat([$this->toolTurn('one'), $this->toolTurn('two')], $requests);
        try {
            $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), maxToolIterations: 1));
            $this->fail('Iteration limit should stop execution.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Maximum tool iterations', $exception->getMessage());
            $this->assertSame(1, $executions);
        }
    }

    public function testReusedIdWithDifferentArgumentsIsRejected(): void
    {
        $executions = 0;
        $tool = new Tool('calculate', 'Calculate', [], function () use (&$executions): string { $executions++; return 'done'; });
        $requests = [];
        $chat = $this->chat([$this->toolTurn('one', '{"n":1}'), $this->toolTurn('one', '{"n":2}')], $requests);
        try {
            $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool)));
            $this->fail('Changed arguments must not reuse an invocation ID.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('reused', $exception->getMessage());
            $this->assertSame(1, $executions);
        }
    }

    public function testTwoCallsInOneTurnExecuteSequentially(): void
    {
        $order = [];
        $tool = new Tool('calculate', 'Calculate', [], function (array $args) use (&$order): string { $order[] = $args['n']; return 'done'; });
        $turn = $this->toolTurn('one', '{"n":1}');
        $turn['choices'][0]['message']['tool_calls'][] = $this->toolTurn('two', '{"n":2}')['choices'][0]['message']['tool_calls'][0];
        $requests = [];
        $chat = $this->chat([$turn, $this->finalTurn()], $requests);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), parallelToolCalls: true));
        $this->assertSame([1, 2], $order);
        $this->assertSame(['user', 'assistant', 'tool', 'tool'], array_column($requests[1]['messages'], 'role'));
    }

    public function testInjectedRegistryRetainsCompletedInvocationAcrossExplicitRecovery(): void
    {
        $executions = 0;
        $registry = new InvocationRegistry();
        $tool = new Tool('calculate', 'Calculate', [], function () use (&$executions): string { $executions++; return 'done'; });
        $requests = [];
        $chat = $this->chat([$this->toolTurn('one'), new Response('failed', 503), $this->toolTurn('one'), $this->finalTurn()], $requests, fn(): InvocationRegistry => $registry);
        $request = new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool));
        try {
            $chat->chat($request);
            $this->fail('The provider should fail after the first invocation.');
        } catch (RuntimeException $exception) {
            $this->assertSame(503, $exception->getCode());
        }
        $chat->chat($request);
        $this->assertSame(1, $executions);
    }

    public function testMcpCollectionUsesItsExistingTransport(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('notify');
        $transport->expects($this->exactly(3))->method('request')->willReturnCallback(
            static function (string $body): string {
                $request = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
                $result = match ($request['method']) {
                    'initialize' => ['protocolVersion' => '2025-03-26', 'capabilities' => []],
                    'tools/list' => ['tools' => [['name' => 'calculate', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]]]],
                    'tools/call' => ['content' => [['type' => 'text', 'text' => 'MCP result']]],
                };
                return json_encode(['jsonrpc' => '2.0', 'id' => $request['id'], 'result' => $result], JSON_THROW_ON_ERROR);
            },
        );
        $requests = [];
        $chat = $this->chat([$this->toolTurn('one'), $this->finalTurn()], $requests);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new McpServer($transport)));
        $this->assertSame('MCP result', $requests[1]['messages'][2]['content']);
    }

    public function testOpenApiCollectionUsesItsExistingHttpClient(): void
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
        $requests = [];
        $chat = $this->chat([$this->toolTurn('one'), $this->finalTurn()], $requests);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new OpenApi($spec, $client)));
        $this->assertSame('OpenAPI result', $requests[1]['messages'][2]['content']);
    }

    public function testChatToolPreservesArrayAndNamedPromptBuilders(): void
    {
        $chat = $this->createMock(ChatInterface::class);
        $result = new ChatResult(new AssistantMessage('Delegated result'), new TokenUsage(), FinishReason::STOP, 'test');
        $chat->expects($this->exactly(2))->method('chat')->with('Explain PHP')->willReturn(new ChatResponse(new Conversation(), $result));
        $array = new ChatTool('delegate', 'Delegate', [], $chat, fn(array $args): string => 'Explain ' . $args['topic']);
        $named = new ChatTool('delegate', 'Delegate', [], $chat, fn(string $topic): string => 'Explain ' . $topic);
        $this->assertSame($chat, $named->getChat());
        $this->assertSame('Delegated result', $array->execute(['topic' => 'PHP']));
        $this->assertSame('Delegated result', $named->execute(['topic' => 'PHP', 'unused' => true]));
    }

    /** @return iterable<string, array{string}> */
    public static function malformedArguments(): iterable
    {
        yield 'invalid JSON' => ['{'];
        yield 'empty string' => [''];
        yield 'array instead of object' => ['[]'];
        yield 'scalar' => ['42'];
    }

    #[DataProvider('malformedArguments')]
    public function testMalformedArgumentsNeverExecute(string $arguments): void
    {
        $tool = new Tool('calculate', 'Calculate', [], fn(): never => $this->fail('Malformed JSON must never execute.'));
        $requests = [];
        $chat = $this->chat([$this->toolTurn('one', $arguments)], $requests);
        $this->expectException(RuntimeException::class);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool)));
    }

    public function testDuplicateIdsInOneResponseAreRejectedBeforeAnyExecution(): void
    {
        $turn = $this->toolTurn('one');
        $turn['choices'][0]['message']['tool_calls'][] = $turn['choices'][0]['message']['tool_calls'][0];
        $tool = new Tool('calculate', 'Calculate', [], fn(): never => $this->fail('Duplicate IDs must never execute.'));
        $requests = [];
        $chat = $this->chat([$turn], $requests);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate tool invocation ID');
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool)));
    }

    public function testMismatchedToolResultIdIsRejected(): void
    {
        $tool = new Tool('calculate', 'Calculate', [], fn(): ToolResult => new ToolResult('wrong', 'done'));
        $requests = [];
        $chat = $this->chat([$this->toolTurn('one')], $requests);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tool result ID does not match');
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool)));
    }

    /** @param list<array|ResponseInterface> $responses */
    private function chat(array $responses, array &$requests, ?Closure $registryFactory = null): Chat
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(count($responses)))->method('sendRequest')->willReturnCallback(
            static function (RequestInterface $request) use (&$responses, &$requests): ResponseInterface {
                $requests[] = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $next = array_shift($responses);
                return $next instanceof ResponseInterface ? $next : new Response(json_encode($next, JSON_THROW_ON_ERROR));
            },
        );
        return new Chat(new OpenAi('key', $client), new Model('tools', capabilities: [Capability::TEXT, Capability::TOOLS]), registryFactory: $registryFactory);
    }

    private function toolTurn(string $id, string $arguments = '{}'): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => 'Calculating', 'tool_calls' => [[
            'id' => $id, 'type' => 'function', 'function' => ['name' => 'calculate', 'arguments' => $arguments],
            'extra_content' => ['google' => ['thought_signature' => 'signature']],
        ]]], 'finish_reason' => 'tool_calls']], 'usage' => ['prompt_tokens' => 2, 'completion_tokens' => 1, 'total_tokens' => 3]];
    }

    private function finalTurn(): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => 'Done'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 2, 'completion_tokens' => 1, 'total_tokens' => 3]];
    }
}
