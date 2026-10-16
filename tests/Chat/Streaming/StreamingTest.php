<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Chat\Streaming;

use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Chat\Guard\GuardException;
use ByCerfrance\LlmApiLib\Chat\Guard\StreamingGuardChat;
use ByCerfrance\LlmApiLib\Chat\Streaming\ChatEvent;
use ByCerfrance\LlmApiLib\Chat\Streaming\ChatStream;
use ByCerfrance\LlmApiLib\Chat\Streaming\StreamControl;
use ByCerfrance\LlmApiLib\Chat\Streaming\StreamingChat;
use ByCerfrance\LlmApiLib\Chat\Streaming\StreamingChatUsage;
use ByCerfrance\LlmApiLib\Chat\ToolChoice;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Model\ParameterPolicy;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\ModelChatBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\JsonTransport;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAi;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAiChatBuilder;
use ByCerfrance\LlmApiLib\Provider\OpenAi\StreamingOpenAi;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\ChatContentBuilder;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Provider\ProviderStreamEvent;
use ByCerfrance\LlmApiLib\Provider\RetryStreamingProvider;
use ByCerfrance\LlmApiLib\Resilience\RetryPolicy;
use ByCerfrance\LlmApiLib\Resilience\RetryRunner;
use ByCerfrance\LlmApiLib\Tests\Transport\Fixture\ChunkResponse;
use ByCerfrance\LlmApiLib\Tests\Transport\Fixture\ChunkTransport;
use ByCerfrance\LlmApiLib\Tool\AbstractTool;
use ByCerfrance\LlmApiLib\Tool\InvocationRegistry;
use ByCerfrance\LlmApiLib\Tool\JsonSchemaArgumentsValidator;
use ByCerfrance\LlmApiLib\Tool\Tool;
use ByCerfrance\LlmApiLib\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Tool\ToolErrorPolicy;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Tool\ToolSchema;
use ByCerfrance\LlmApiLib\Transport\SseDecoder;
use ByCerfrance\LlmApiLib\Transport\SseEvent;
use ByCerfrance\LlmApiLib\Usage\Measurement;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use ByCerfrance\LlmApiLib\Usage\UsageAccumulator;
use ByCerfrance\LlmApiLib\Usage\UsageCollector;
use ByCerfrance\LlmApiLib\Usage\UsageComposite;
use ByCerfrance\LlmApiLib\Usage\UsageRecord;
use ByCerfrance\LlmApiLib\Usage\UsageSnapshot;
use ByCerfrance\LlmApiLib\Usage\UsageTotal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use RuntimeException;

#[CoversClass(StreamingChat::class)]
#[CoversClass(StreamingOpenAi::class)]
#[CoversClass(ChatStream::class)]
#[CoversClass(StreamControl::class)]
#[CoversClass(RetryStreamingProvider::class)]
#[CoversClass(StreamingChatUsage::class)]
#[CoversClass(StreamingGuardChat::class)]
#[UsesClass(Chat::class)]
#[UsesClass(ChatEvent::class)]
#[UsesClass(ProviderStreamEvent::class)]
#[UsesClass(SseDecoder::class)]
#[UsesClass(SseEvent::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(Model::class)]
#[UsesClass(Capability::class)]
#[UsesClass(ParameterPolicy::class)]
#[UsesClass(AbstractOpenAiCompatible::class)]
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
#[UsesClass(AbstractTool::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolCollection::class)]
#[UsesClass(ToolResult::class)]
#[UsesClass(ToolSchema::class)]
#[UsesClass(ToolErrorPolicy::class)]
#[UsesClass(ToolChoice::class)]
#[UsesClass(ToolExecutor::class)]
#[UsesClass(InvocationRegistry::class)]
#[UsesClass(JsonSchemaArgumentsValidator::class)]
#[UsesClass(FailureKind::class)]
#[UsesClass(ProviderException::class)]
#[UsesClass(RetryPolicy::class)]
#[UsesClass(RetryRunner::class)]
#[UsesClass(GuardException::class)]
#[UsesClass(Measurement::class)]
#[UsesClass(UsageRecord::class)]
#[UsesClass(UsageCollector::class)]
#[UsesClass(UsageAccumulator::class)]
#[UsesClass(UsageComposite::class)]
#[UsesClass(UsageSnapshot::class)]
#[UsesClass(UsageTotal::class)]
#[UsesClass(JsonTransport::class)]
#[UsesClass(OpenAiChatBuilder::class)]
class StreamingTest extends TestCase
{
    public function testProgressiveTextAndFinalUsageAreAssembled(): void
    {
        $response = new ChunkResponse([$this->delta(['content' => 'Hel']), $this->delta(['content' => 'lo'], 'stop'), $this->usage(), "data: [DONE]\n\n"]);
        $transport = new ChunkTransport([$response]);
        $stream = $this->chat($transport)->stream('Hi');
        $iterator = $stream->getIterator();
        $iterator->rewind();
        $this->assertSame('Hel', $iterator->current()->text);
        $this->assertSame(1, $response->readCount);
        $events = [];
        while ($iterator->valid()) { $events[] = $iterator->current(); $iterator->next(); }
        $final = $events[array_key_last($events)]->response;
        $this->assertSame('Hello', (string) $final->getResult()->getMessage()->getContent());
        $this->assertSame(15, $final->getUsage()->getTotalTokens());
        $this->assertTrue($response->closed);
        $body = json_decode((string) $transport->requests[0]->getBody(), true);
        $this->assertTrue($body['stream']);
        $this->assertTrue($body['stream_options']['include_usage']);
    }

    public function testFragmentedToolsExecuteOnlyAfterTerminalMarkerAndContinue(): void
    {
        $first = new ChunkResponse(str_split(
            $this->delta(['tool_calls' => [['index' => 0, 'id' => 'call', 'type' => 'function', 'function' => ['name' => 'double', 'arguments' => '{"n":']]]])
            . $this->delta(['tool_calls' => [['index' => 0, 'function' => ['arguments' => '2}']]]], 'tool_calls') . "data: [DONE]\n\n", 7));
        $second = new ChunkResponse([$this->delta(['content' => '4'], 'stop') . $this->usage() . "data: [DONE]\n\n"]);
        $transport = new ChunkTransport([$first, $second]);
        $executions = 0;
        $tool = new Tool('double', 'Double', ['type' => 'object', 'required' => ['n']], function (array $args) use (&$executions, $first): string {
            $this->assertTrue($first->closed);
            $executions++;
            return (string) ($args['n'] * 2);
        });
        $events = iterator_to_array($this->chat($transport)->stream(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool))), false);
        $this->assertSame(1, $executions);
        $this->assertSame(['tool.delta', 'tool.delta', 'tool.started', 'tool.completed', 'text.delta', 'completed'], array_column($events, 'type'));
        $this->assertSame('call', json_decode((string) $transport->requests[1]->getBody(), true)['messages'][2]['tool_call_id']);
        $this->assertCount(4, $events[5]->response->toConversation());
        $this->assertNull($events[5]->response->getUsage()->getTotalTokens());
    }

    public function testExplicitCloseStopsPendingToolsAndReleasesResponse(): void
    {
        $response = new ChunkResponse([$this->delta(['content' => 'partial']), $this->delta([], 'stop') . "data: [DONE]\n\n"]);
        $stream = $this->chat(new ChunkTransport([$response]))->stream('Hi');
        $iterator = $stream->getIterator();
        $iterator->rewind();
        $stream->close();
        $this->assertTrue($response->closed);
        $iterator->next();
        $this->assertFalse($iterator->valid());
        $this->assertSame(1, $response->readCount);
    }

    public function testCancellationBeforeToolExecutionDoesNotInvokeCallback(): void
    {
        $response = new ChunkResponse([$this->delta(['tool_calls' => [['index' => 0, 'id' => 'call', 'type' => 'function',
            'function' => ['name' => 'work', 'arguments' => '{}']]]], 'tool_calls') . "data: [DONE]\n\n"]);
        $tool = new Tool('work', 'Work', [], fn(): never => $this->fail('Cancelled tools must not execute.'));
        $stream = $this->chat(new ChunkTransport([$response]))->stream(new ChatRequest(new Conversation(['Work']), tools: new ToolCollection($tool)));
        foreach ($stream as $event) {
            if ($event->type === 'tool.started') { $stream->close(); break; }
        }
        $this->assertTrue($response->closed);
    }

    public function testIncompleteToolArgumentsAreNotExecuted(): void
    {
        $response = new ChunkResponse([$this->delta(['tool_calls' => [['index' => 0, 'id' => 'call', 'type' => 'function',
            'function' => ['name' => 'work', 'arguments' => '{']]]], 'tool_calls') . "data: [DONE]\n\n"]);
        $tool = new Tool('work', 'Work', [], fn(): never => $this->fail('Malformed arguments must not execute.'));
        $stream = $this->chat(new ChunkTransport([$response]))->stream(new ChatRequest(new Conversation(['Work']), tools: new ToolCollection($tool)));
        try {
            iterator_to_array($stream);
            $this->fail('Invalid arguments must fail at turn assembly.');
        } catch (ProviderException $failure) {
            $this->assertSame(FailureKind::INVALID_RESPONSE, $failure->getKind());
            $this->assertTrue($response->closed);
        }
    }

    public function testInterruptedStreamNeverRetriesAfterOutput(): void
    {
        $response = new ChunkResponse([$this->delta(['content' => 'visible'])]);
        $transport = new ChunkTransport([$response]);
        try {
            iterator_to_array($this->chat($transport, true)->stream('Hi'));
            $this->fail('Interrupted stream must fail.');
        } catch (ProviderException) {
            $this->assertCount(1, $transport->requests);
            $this->assertTrue($response->closed);
        }
    }

    public function testRetryBeforeEmissionIsAllowed(): void
    {
        $failed = new ChunkResponse([], 503);
        $success = new ChunkResponse([$this->delta(['content' => 'OK'], 'stop') . "data: [DONE]\n\n"]);
        $transport = new ChunkTransport([$failed, $success]);
        $events = iterator_to_array($this->chat($transport, true)->stream('Hi'));
        $this->assertCount(2, $transport->requests);
        $this->assertTrue($failed->closed);
        $this->assertSame('completed', $events[1]->type);
    }

    public function testStreamingGuardRejectsFinalResponseWithoutReplayingText(): void
    {
        $response = new ChunkResponse([$this->delta(['content' => 'visible'], 'stop') . $this->usage() . "data: [DONE]\n\n"]);
        $collector = new UsageCollector();
        $chat = new StreamingGuardChat(new StreamingChatUsage($this->chat(new ChunkTransport([$response])), $collector),
            static function (): void { throw new RuntimeException('Rejected'); });
        $types = [];
        try {
            foreach ($chat->stream('Hi') as $event) { $types[] = $event->type; }
            $this->fail('Guard must reject.');
        } catch (GuardException) {
            $this->assertSame(['text.delta'], $types);
            $this->assertSame(15, $collector->snapshot()->get('total', 'token')->getValue());
            $this->assertTrue($response->closed);
        }
    }

    private function chat(ChunkTransport $transport, bool $retry = false): StreamingChat
    {
        $provider = new StreamingOpenAi('key', $this->createStub(ClientInterface::class), $transport);
        return new StreamingChat($retry ? new RetryStreamingProvider($provider, new RetryPolicy(initialDelayMs: 0)) : $provider, new Model('model'));
    }

    private function delta(array $delta, ?string $finish = null): string
    {
        return 'data: ' . json_encode(['choices' => [['index' => 0, 'delta' => (object) $delta, 'finish_reason' => $finish]]], JSON_THROW_ON_ERROR) . "\n\n";
    }

    private function usage(): string
    {
        return "data: {\"choices\":[],\"usage\":{\"prompt_tokens\":10,\"completion_tokens\":5,\"total_tokens\":15}}\n\n";
    }
}
