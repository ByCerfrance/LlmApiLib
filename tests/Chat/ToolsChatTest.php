<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Chat;

use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatInterface;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Chat\ToolChoice;
use ByCerfrance\LlmApiLib\Content\JsonContent;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Observation\ExecutionEvent;
use ByCerfrance\LlmApiLib\Observation\ExecutionObserverInterface;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequestInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Tests\Fixture\ScriptedChatProvider;
use ByCerfrance\LlmApiLib\Tool\ChatTool;
use ByCerfrance\LlmApiLib\Tool\InvocationRegistry;
use ByCerfrance\LlmApiLib\Tool\JsonSchemaArgumentsValidator;
use ByCerfrance\LlmApiLib\Tool\Tool;
use ByCerfrance\LlmApiLib\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Tool\ToolErrorPolicy;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Tool\ToolSchema;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Chat::class)]
#[CoversClass(ToolExecutor::class)]
#[CoversClass(InvocationRegistry::class)]
#[CoversClass(JsonSchemaArgumentsValidator::class)]
#[CoversClass(ChatTool::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(Model::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolCollection::class)]
#[UsesClass(ToolResult::class)]
#[UsesClass(ToolSchema::class)]
#[UsesClass(TokenUsage::class)]
#[UsesClass(JsonContent::class)]
#[UsesClass(ProviderException::class)]
#[UsesClass(ExecutionEvent::class)]
class ToolsChatTest extends TestCase
{
    public function testRepeatedInvocationIsCachedButNewIdExecutesAgain(): void
    {
        $executions = 0;
        $tool = new Tool('calculate', 'Calculate', ['type' => 'object', 'required' => ['n'], 'properties' => ['n' => ['type' => 'integer']]],
            function (array $args) use (&$executions): string { $executions++; return (string) ($args['n'] * 2); });
        [$chat, $provider] = $this->chat([
            $this->toolTurn($this->call('one', '{"n":2}')), $this->toolTurn($this->call('one', '{"n":2}')),
            $this->toolTurn($this->call('two', '{"n":2}')), $this->finalTurn(),
        ]);
        $request = new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), maxToolIterations: 3, toolChoice: ToolChoice::REQUIRED);
        $response = $chat->chat($request);
        $this->assertSame(2, $executions);
        $this->assertCount(4, $response->getTurns());
        $this->assertSame(12, $response->getUsage()->getTotalTokens());
        $this->assertNull($response->getUsage()->getCachedTokens());
        $this->assertSame(3, $response->getResult()->getUsage()->getTotalTokens());
        $this->assertCount(1, $request->getConversation());
        $this->assertCount(8, $response->toConversation());
        $this->assertSame(ToolChoice::REQUIRED, $provider->requests[0]->getRequest()->getToolChoice());
        $this->assertSame(ToolChoice::AUTO, $provider->requests[1]->getRequest()->getToolChoice());
        $messages = $this->messages($provider->requests[1]);
        $this->assertInstanceOf(AssistantMessage::class, $messages[1]);
        $this->assertInstanceOf(ToolResult::class, $messages[2]);
        $this->assertSame('one', $messages[2]->getToolCallId());
        $this->assertSame('4', (string) $messages[2]->getContent());
    }

    public function testCompletedHistoryCanBeForkedWithoutReplayingTools(): void
    {
        $executions = 0;
        $tool = new Tool('calculate', 'Calculate', ['type' => 'object'], function () use (&$executions): string { $executions++; return 'done'; });
        [$chat] = $this->chat([
            $this->toolTurn($this->call('one')), $this->finalTurn(),
            $this->toolTurn($this->call('one')), $this->finalTurn(), $this->finalTurn(),
        ]);
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
        [$chat, $provider] = $this->chat([
            $this->toolTurn($this->call('bad', '{"n":"not a number"}')), $this->toolTurn($this->call('good', '{"n":2}')), $this->finalTurn(),
        ]);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), toolErrorPolicy: ToolErrorPolicy::RETURN_ERROR));
        $this->assertSame(1, $executions);
        $this->assertSame('invalid_arguments', $this->error($provider->requests[1], 2)['type']);
    }

    public function testNestedEmptyObjectIsNotConfusedWithAnArray(): void
    {
        $executions = 0;
        $tool = new Tool('calculate', 'Calculate', ['type' => 'object', 'properties' => ['options' => ['type' => 'object', 'properties' => []]], 'required' => ['options']],
            function () use (&$executions): string { $executions++; return 'done'; });
        [$chat, $provider] = $this->chat([
            $this->toolTurn($this->call('bad', '{"options":[]}')), $this->toolTurn($this->call('good', '{"options":{}}')), $this->finalTurn(),
        ]);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), toolErrorPolicy: ToolErrorPolicy::RETURN_ERROR));
        $this->assertSame(1, $executions);
        $this->assertSame('invalid_arguments', $this->error($provider->requests[1], 2)['type']);
    }

    public function testUnknownToolCanReturnAnErrorWithoutExecutingAnotherTool(): void
    {
        [$chat, $provider] = $this->chat([$this->toolTurn($this->call('missing')), $this->finalTurn()]);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection(), toolErrorPolicy: ToolErrorPolicy::RETURN_ERROR));
        $this->assertSame('unknown_tool', $this->error($provider->requests[1], 2)['type']);
    }

    public function testToolFailureIsRecordedAndNotRepeated(): void
    {
        $executions = 0;
        $tool = new Tool('calculate', 'Calculate', [], function () use (&$executions): never { $executions++; throw new RuntimeException('private details'); });
        [$chat, $provider] = $this->chat([$this->toolTurn($this->call('one')), $this->toolTurn($this->call('one')), $this->finalTurn()]);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), toolErrorPolicy: ToolErrorPolicy::RETURN_ERROR));
        $this->assertSame(1, $executions);
        $error = $this->error($provider->requests[1], 2);
        $this->assertSame('execution_failed', $error['type']);
        $this->assertStringNotContainsString('private', $error['message']);
    }

    public function testStopPolicyStopsAfterValidationFailure(): void
    {
        $tool = new Tool('calculate', 'Calculate', ['type' => 'object', 'required' => ['n']], fn(): never => $this->fail('Invalid arguments must not execute.'));
        [$chat] = $this->chat([$this->toolTurn($this->call('one'))]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid_arguments');
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool)));
    }

    public function testIterationLimitPreventsTheNextToolRound(): void
    {
        $executions = 0;
        $tool = new Tool('calculate', 'Calculate', [], function () use (&$executions): string { $executions++; return 'done'; });
        [$chat] = $this->chat([$this->toolTurn($this->call('one')), $this->toolTurn($this->call('two'))]);
        try {
            $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), maxToolIterations: 1));
            $this->fail('Iteration limit should stop execution.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Maximum tool iterations', $exception->getMessage());
            $this->assertSame(1, $executions);
        }
    }

    public function testToolCallsAreRejectedWhenToolsAreDisabled(): void
    {
        [$chat] = $this->chat([$this->toolTurn($this->call('one'))]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tool execution was not enabled');
        $chat->chat('Calculate');
    }

    public function testReusedIdWithDifferentArgumentsIsRejected(): void
    {
        $executions = 0;
        $tool = new Tool('calculate', 'Calculate', [], function () use (&$executions): string { $executions++; return 'done'; });
        [$chat] = $this->chat([$this->toolTurn($this->call('one', '{"n":1}')), $this->toolTurn($this->call('one', '{"n":2}'))]);
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
        [$chat, $provider] = $this->chat([$this->toolTurn($this->call('one', '{"n":1}'), $this->call('two', '{"n":2}')), $this->finalTurn()]);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), parallelToolCalls: true));
        $this->assertSame([1, 2], $order);
        $messages = $this->messages($provider->requests[1]);
        $this->assertCount(4, $messages);
        $this->assertSame(['one', 'two'], [$messages[2]->getToolCallId(), $messages[3]->getToolCallId()]);
    }

    public function testInjectedRegistryRetainsCompletedInvocationAcrossExplicitRecovery(): void
    {
        $executions = 0;
        $registry = new InvocationRegistry();
        $tool = new Tool('calculate', 'Calculate', [], function () use (&$executions): string { $executions++; return 'done'; });
        [$chat] = $this->chat([
            $this->toolTurn($this->call('one')), ScriptedChatProvider::failure(FailureKind::UNAVAILABLE, 503),
            $this->toolTurn($this->call('one')), $this->finalTurn(),
        ], fn(): InvocationRegistry => $registry);
        $request = new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool));
        try {
            $chat->chat($request);
            $this->fail('The provider should fail after the first invocation.');
        } catch (ProviderException $exception) {
            $this->assertSame(503, $exception->getCode());
        }
        $chat->chat($request);
        $this->assertSame(1, $executions);
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

    public function testMismatchedToolResultIdIsRejected(): void
    {
        $tool = new Tool('calculate', 'Calculate', [], fn(): ToolResult => new ToolResult('wrong', 'done'));
        [$chat] = $this->chat([$this->toolTurn($this->call('one'))]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tool result ID does not match');
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool)));
    }

    public function testToolObservationDistinguishesExecutionFromReuse(): void
    {
        $phases = [];
        $observer = $this->createMock(ExecutionObserverInterface::class);
        $observer->expects($this->exactly(3))->method('observe')->willReturnCallback(static function (ExecutionEvent $event) use (&$phases): void {
            $phases[] = $event->phase;
        });
        $executor = new ToolExecutor(observer: $observer);
        $registry = new InvocationRegistry();
        $tools = new ToolCollection(new Tool('calculate', 'Calculate', [], static fn(): string => 'done'));
        $call = new ToolCall('one', 'calculate', []);
        $executor->execute($call, $tools, $registry, ToolErrorPolicy::STOP);
        $executor->execute($call, $tools, $registry, ToolErrorPolicy::STOP);
        $this->assertSame(['tool.started', 'tool.completed', 'tool.reused'], $phases);
    }

    /**
     * @param list<ChatResult|ProviderException> $script
     *
     * @return array{Chat, ScriptedChatProvider}
     */
    private function chat(array $script, ?Closure $registryFactory = null): array
    {
        $provider = new ScriptedChatProvider($script);

        return [new Chat($provider, new Model('tools'), registryFactory: $registryFactory), $provider];
    }

    private function call(string $id, string $arguments = '{}'): ToolCall
    {
        return ToolCall::fromArray(['id' => $id, 'type' => 'function', 'function' => ['name' => 'calculate', 'arguments' => $arguments]]);
    }

    private function toolTurn(ToolCall ...$calls): ChatResult
    {
        return new ChatResult(new AssistantMessage('Calculating', $calls), new TokenUsage(2, 1, 3), FinishReason::TOOL_CALLS, 'scripted');
    }

    private function finalTurn(): ChatResult
    {
        return ScriptedChatProvider::answer(usage: new TokenUsage(2, 1, 3));
    }

    /** @return list<mixed> Messages sent with a provider request. */
    private function messages(ProviderChatRequestInterface $request): array
    {
        return iterator_to_array($request->getRequest()->getConversation(), false);
    }

    /** @return array{type: string, message: string} Structured error returned to the model. */
    private function error(ProviderChatRequestInterface $request, int $index): array
    {
        $result = $this->messages($request)[$index];
        $this->assertInstanceOf(ToolResult::class, $result);

        return json_decode((string) $result->getContent(), true, flags: JSON_THROW_ON_ERROR)['error'];
    }
}
