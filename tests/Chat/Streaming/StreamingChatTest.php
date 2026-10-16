<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Chat\Streaming;

use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\ChatUsage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Chat\Guard\GuardChat;
use ByCerfrance\LlmApiLib\Chat\Guard\GuardException;
use ByCerfrance\LlmApiLib\Chat\Guard\StreamingGuardChat;
use ByCerfrance\LlmApiLib\Chat\Streaming\ChatEvent;
use ByCerfrance\LlmApiLib\Chat\Streaming\ChatStream;
use ByCerfrance\LlmApiLib\Chat\Streaming\StreamControl;
use ByCerfrance\LlmApiLib\Chat\Streaming\StreamingChat;
use ByCerfrance\LlmApiLib\Chat\Streaming\StreamingChatUsage;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Provider\ProviderStreamEvent;
use ByCerfrance\LlmApiLib\Provider\RetryProvider;
use ByCerfrance\LlmApiLib\Provider\RetryStreamingProvider;
use ByCerfrance\LlmApiLib\Resilience\RetryPolicy;
use ByCerfrance\LlmApiLib\Resilience\RetryRunner;
use ByCerfrance\LlmApiLib\Tests\Fixture\ScriptedChatProvider;
use ByCerfrance\LlmApiLib\Tests\Fixture\ScriptedStreamingProvider;
use ByCerfrance\LlmApiLib\Tool\AbstractTool;
use ByCerfrance\LlmApiLib\Tool\InvocationRegistry;
use ByCerfrance\LlmApiLib\Tool\JsonSchemaArgumentsValidator;
use ByCerfrance\LlmApiLib\Tool\Tool;
use ByCerfrance\LlmApiLib\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Tool\ToolSchema;
use ByCerfrance\LlmApiLib\Usage\Measurement;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use ByCerfrance\LlmApiLib\Usage\UsageAccumulator;
use ByCerfrance\LlmApiLib\Usage\UsageCollector;
use ByCerfrance\LlmApiLib\Usage\UsageRecord;
use ByCerfrance\LlmApiLib\Usage\UsageSnapshot;
use ByCerfrance\LlmApiLib\Usage\UsageTotal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(StreamingChat::class)]
#[CoversClass(ChatStream::class)]
#[CoversClass(StreamControl::class)]
#[CoversClass(RetryStreamingProvider::class)]
#[CoversClass(StreamingChatUsage::class)]
#[CoversClass(StreamingGuardChat::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(ChatEvent::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(Model::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(ProviderStreamEvent::class)]
#[UsesClass(JsonSchemaArgumentsValidator::class)]
#[UsesClass(ToolExecutor::class)]
#[UsesClass(TokenUsage::class)]
#[UsesClass(AbstractTool::class)]
#[UsesClass(InvocationRegistry::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolCollection::class)]
#[UsesClass(ToolResult::class)]
#[UsesClass(ToolSchema::class)]
#[UsesClass(ProviderException::class)]
#[UsesClass(RetryPolicy::class)]
#[UsesClass(RetryRunner::class)]
#[UsesClass(RetryProvider::class)]
#[UsesClass(GuardException::class)]
#[UsesClass(Measurement::class)]
#[UsesClass(UsageAccumulator::class)]
#[UsesClass(UsageCollector::class)]
#[UsesClass(UsageRecord::class)]
#[UsesClass(UsageSnapshot::class)]
#[UsesClass(UsageTotal::class)]
#[UsesClass(ChatUsage::class)]
#[UsesClass(GuardChat::class)]
class StreamingChatTest extends TestCase
{
    public function testProgressiveTextAndFinalResponse(): void
    {
        $provider = new ScriptedStreamingProvider([
            ScriptedStreamingProvider::turn(ScriptedChatProvider::answer('Hello', usage: new TokenUsage(10, 5, 15)), 'Hel', 'lo'),
        ]);
        $events = iterator_to_array((new StreamingChat($provider, new Model('model')))->stream('Hi'), false);
        $this->assertSame(['text.delta', 'text.delta', 'completed'], array_column($events, 'type'));
        $this->assertSame(['Hel', 'lo'], [$events[0]->text, $events[1]->text]);
        $this->assertSame('Hello', (string) $events[2]->response->getResult()->getMessage()->getContent());
        $this->assertSame(15, $events[2]->response->getUsage()->getTotalTokens());
        $this->assertCount(2, $events[2]->response->toConversation());
        $this->assertSame(1, $provider->released);
    }

    public function testToolsExecuteAfterTheTurnAndContinue(): void
    {
        $provider = new ScriptedStreamingProvider([
            ScriptedStreamingProvider::turn(ScriptedChatProvider::toolTurn(new ToolCall('call', 'double', ['n' => 2]))),
            ScriptedStreamingProvider::turn(ScriptedChatProvider::answer('4'), '4'),
        ]);
        $executions = 0;
        $tool = new Tool('double', 'Double', ['type' => 'object', 'required' => ['n']], function (array $args) use (&$executions, $provider): string {
            $this->assertSame(1, $provider->released);
            $executions++;
            return (string) ($args['n'] * 2);
        });
        $events = iterator_to_array((new StreamingChat($provider, new Model('model')))->stream(
            new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool)),
        ), false);
        $this->assertSame(1, $executions);
        $this->assertSame(['tool.started', 'tool.completed', 'text.delta', 'completed'], array_column($events, 'type'));
        $this->assertSame('call', $events[1]->toolResult->getToolCallId());
        $this->assertCount(4, $events[3]->response->toConversation());
        $this->assertCount(2, $events[3]->response->getTurns());
    }

    public function testToolCallsWithoutToolsAreRejected(): void
    {
        $provider = new ScriptedStreamingProvider([ScriptedStreamingProvider::turn(ScriptedChatProvider::toolTurn(new ToolCall('call', 'work', [])))]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tool execution was not enabled');
        iterator_to_array((new StreamingChat($provider, new Model('model')))->stream('Work'));
    }

    public function testExplicitCloseStopsTheProviderStream(): void
    {
        $provider = new ScriptedStreamingProvider([ScriptedStreamingProvider::turn(ScriptedChatProvider::answer(), 'partial', 'rest')]);
        $stream = (new StreamingChat($provider, new Model('model')))->stream('Hi');
        $iterator = $stream->getIterator();
        $iterator->rewind();
        $this->assertSame('partial', $iterator->current()->text);
        $stream->close();
        $iterator->next();
        $this->assertFalse($iterator->valid());
        $this->assertSame(1, $provider->released);
    }

    public function testCancellationBeforeToolExecutionDoesNotInvokeCallback(): void
    {
        $provider = new ScriptedStreamingProvider([ScriptedStreamingProvider::turn(ScriptedChatProvider::toolTurn(new ToolCall('call', 'work', [])))]);
        $tool = new Tool('work', 'Work', [], fn(): never => $this->fail('Cancelled tools must not execute.'));
        $stream = (new StreamingChat($provider, new Model('model')))->stream(new ChatRequest(new Conversation(['Work']), tools: new ToolCollection($tool)));
        $types = [];
        foreach ($stream as $event) {
            $types[] = $event->type;
            if ($event->type === 'tool.started') {
                $stream->close();
            }
        }
        $this->assertSame(['tool.started'], $types);
    }

    public function testStreamCanOnlyBeConsumedOnce(): void
    {
        $provider = new ScriptedStreamingProvider([ScriptedStreamingProvider::turn(ScriptedChatProvider::answer())]);
        $stream = (new StreamingChat($provider, new Model('model')))->stream('Hi');
        iterator_to_array($stream);
        $this->expectException(RuntimeException::class);
        iterator_to_array($stream);
    }

    public function testClosersRunOnceAndCanBeRemoved(): void
    {
        $control = new StreamControl();
        $calls = [];
        $control->onClose(static function () use (&$calls): void { $calls[] = 'kept'; });
        $removed = $control->onClose(static function () use (&$calls): void { $calls[] = 'removed'; });
        $control->remove($removed);
        $control->close();
        $control->close();
        $control->onClose(static function () use (&$calls): void { $calls[] = 'late'; });
        $this->assertTrue($control->isClosed());
        $this->assertSame(['kept', 'late'], $calls);
    }

    public function testRetryBeforeEmissionIsAllowed(): void
    {
        $provider = new ScriptedStreamingProvider([
            [ScriptedChatProvider::failure(FailureKind::UNAVAILABLE, 503)],
            ScriptedStreamingProvider::turn(ScriptedChatProvider::answer('OK'), 'OK'),
        ]);
        $chat = new StreamingChat(new RetryStreamingProvider($provider, new RetryPolicy(initialDelayMs: 0)), new Model('model'));
        $events = iterator_to_array($chat->stream('Hi'), false);
        $this->assertCount(2, $provider->requests);
        $this->assertSame(['text.delta', 'completed'], array_column($events, 'type'));
    }

    public function testInterruptedStreamNeverRetriesAfterOutput(): void
    {
        $failure = ScriptedChatProvider::failure(FailureKind::UNAVAILABLE, 503);
        $provider = new ScriptedStreamingProvider([[new ProviderStreamEvent('text.delta', 'visible'), $failure]]);
        $chat = new StreamingChat(new RetryStreamingProvider($provider, new RetryPolicy(initialDelayMs: 0)), new Model('model'));
        try {
            iterator_to_array($chat->stream('Hi'));
            $this->fail('Interrupted stream must fail.');
        } catch (ProviderException $exception) {
            $this->assertSame($failure, $exception);
            $this->assertCount(1, $provider->requests);
        }
    }

    public function testSynchronousCompletionThroughRetryingProvider(): void
    {
        $provider = new ScriptedStreamingProvider([ScriptedStreamingProvider::turn(ScriptedChatProvider::answer('Done'))]);
        $response = (new StreamingChat(new RetryStreamingProvider($provider), new Model('model')))->chat('Hi');
        $this->assertSame('Done', (string) $response->getResult()->getMessage()->getContent());
    }

    public function testStreamingGuardRejectsFinalResponseWithoutReplayingText(): void
    {
        $provider = new ScriptedStreamingProvider([
            ScriptedStreamingProvider::turn(ScriptedChatProvider::answer('visible', usage: new TokenUsage(10, 5, 15)), 'visible'),
        ]);
        $collector = new UsageCollector();
        $chat = new StreamingGuardChat(new StreamingChatUsage(new StreamingChat($provider, new Model('model')), $collector),
            static function (): void { throw new RuntimeException('Rejected'); });
        $types = [];
        try {
            foreach ($chat->stream('Hi') as $event) {
                $types[] = $event->type;
            }
            $this->fail('Guard must reject.');
        } catch (GuardException) {
            $this->assertSame(['text.delta'], $types);
            $this->assertSame(15, $collector->snapshot()->get('total', 'token')->getValue());
            $this->assertSame(1, $provider->released);
        }
    }

    public function testSynchronousCallsGoThroughGuardAndUsage(): void
    {
        $provider = new ScriptedStreamingProvider([ScriptedStreamingProvider::turn(ScriptedChatProvider::answer(usage: new TokenUsage(1, 1, 2)))]);
        $collector = new UsageCollector();
        $guarded = 0;
        $chat = new StreamingGuardChat(new StreamingChatUsage(new StreamingChat($provider, new Model('model')), $collector),
            static function () use (&$guarded): void { $guarded++; });
        $chat->chat('Hi');
        $this->assertSame(1, $guarded);
        $this->assertSame(2, $collector->snapshot()->get('total', 'token')->getValue());
    }
}
