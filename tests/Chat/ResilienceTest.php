<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Chat;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Chat\Guard\FinishReasonGuardChat;
use ByCerfrance\LlmApiLib\Chat\Guard\GuardChat;
use ByCerfrance\LlmApiLib\Chat\Guard\GuardException;
use ByCerfrance\LlmApiLib\Chat\RetryChat;
use ByCerfrance\LlmApiLib\Chat\ToolChoice;
use ByCerfrance\LlmApiLib\Content\NullContent;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Model\ParameterPolicy;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\ModelChatBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\FailoverProvider;
use ByCerfrance\LlmApiLib\Provider\Failure;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\ModelProvider;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAi;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\ChatContentBuilder;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Provider\RetryProvider;
use ByCerfrance\LlmApiLib\Resilience\RetryPolicy;
use ByCerfrance\LlmApiLib\Resilience\RetryRunner;
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
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(RetryProvider::class)]
#[CoversClass(FailoverProvider::class)]
#[CoversClass(ModelProvider::class)]
#[CoversClass(RetryPolicy::class)]
#[CoversClass(RetryRunner::class)]
#[CoversClass(RetryChat::class)]
#[CoversClass(GuardChat::class)]
#[CoversClass(FinishReasonGuardChat::class)]
#[CoversClass(Failure::class)]
#[UsesClass(ProviderException::class)]
#[UsesClass(FailureKind::class)]
#[UsesClass(GuardException::class)]
#[UsesClass(Chat::class)]
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
#[UsesClass(NullContent::class)]
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
class ResilienceTest extends TestCase
{
    public function testRetryAfterToolDoesNotReplayItsCallback(): void
    {
        $executions = 0;
        $requests = [];
        $sleeps = [];
        $client = $this->client([$this->toolTurn(), new Response('private error', 503, headers: ['Retry-After' => '2']), $this->answer()], $requests);
        $retry = new RetryProvider(new OpenAi('secret', $client), runner: new RetryRunner(static function (int $ms) use (&$sleeps): void { $sleeps[] = $ms; }));
        $tool = new Tool('work', 'Work', [], function () use (&$executions): string { $executions++; return 'completed'; });
        $result = (new Chat($retry, new Model('model')))->chat(new ChatRequest(new Conversation(['Hi']), tools: new ToolCollection($tool)));
        $this->assertSame(1, $executions);
        $this->assertSame([2000], $sleeps);
        $this->assertSame($requests[1], $requests[2]);
        $this->assertCount(2, $result->getTurns());
    }

    public function testAuthenticationDoesNotRetryAndRetainsSafeMetadata(): void
    {
        $requests = [];
        $client = $this->client([new Response('secret response', 401, headers: ['x-request-id' => 'req-1'])], $requests);
        try {
            (new Chat(new RetryProvider(new OpenAi('secret', $client)), new Model('model')))->chat('Hi');
            $this->fail('Expected authentication failure.');
        } catch (ProviderException $failure) {
            $this->assertSame(FailureKind::AUTHENTICATION, $failure->getKind());
            $this->assertSame(401, $failure->getCode());
            $this->assertSame('req-1', $failure->getRequestId());
            $this->assertSame('OpenAi', $failure->getProviderId());
            $this->assertSame('', $failure->getBody());
            $this->assertStringNotContainsString('secret', $failure->getMessage());
        }
    }

    public function testBoundedBackoffAndServerMinimum(): void
    {
        $policy = new RetryPolicy(5, 100, 2, 250);
        $this->assertSame(100, $policy->delay(1));
        $this->assertSame(200, $policy->delay(2));
        $this->assertSame(250, $policy->delay(3));
        $this->assertNull($policy->delay(5));
        $this->assertNull($policy->delay(1, new ProviderException('limited', kind: FailureKind::RATE_LIMIT, retryAfterMs: 1000)));
        $failure = Failure::fromResponse('test', new Response('', 429, headers: ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 60)]));
        $this->assertGreaterThanOrEqual(59000, $failure->getRetryAfterMs());
        $this->assertLessThanOrEqual(60000, $failure->getRetryAfterMs());
    }

    public function testFailoverChangesOnlyFailedTurnAndAllowsConfiguredModels(): void
    {
        $executions = 0;
        $firstRequests = [];
        $secondRequests = [];
        $first = new OpenAi('a', $this->client([$this->toolTurn(), new Response('', 503)], $firstRequests), id: 'first');
        $second = new OpenAi('b', $this->client([$this->answer()], $secondRequests), id: 'second');
        $provider = new FailoverProvider([$first, new ModelProvider($second, new Model('fallback-model'))]);
        $tool = new Tool('work', 'Work', [], function () use (&$executions): string { $executions++; return 'completed'; });
        $response = (new Chat($provider, new Model('primary-model')))->chat(new ChatRequest(new Conversation(['Hi']), tools: new ToolCollection($tool)));
        $this->assertSame(1, $executions);
        $this->assertSame('fallback-model', $secondRequests[0]['model']);
        $this->assertSame($firstRequests[1]['messages'], $secondRequests[0]['messages']);
        $this->assertSame('second', $response->getResult()->getProviderId());
    }

    public function testOpaqueContinuationRequiresPinnedProvider(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $provider = new FailoverProvider([new OpenAi('a', $client), new OpenAi('b', $client)]);
        $request = new ProviderChatRequest(new Model('model'), new ChatRequest(new Conversation([
            new AssistantMessage(null, [new ToolCall('id', 'work', [], ['extra_content' => ['signature' => 'opaque']])]),
            new ToolResult('id', 'done'),
        ])));
        $this->expectException(InvalidArgumentException::class);
        $provider->complete($request);
    }

    public function testGuardInsideRetryCanRegenerateButOutsideCannot(): void
    {
        $requests = [];
        $base = new Chat(new OpenAi('key', $this->client([$this->answer('length'), $this->answer()], $requests)), new Model('model'));
        $result = (new RetryChat(new FinishReasonGuardChat($base), new RetryPolicy(initialDelayMs: 0), retryOnGuard: true))->chat('Hi');
        $this->assertSame(FinishReason::STOP, $result->getResult()->getFinishReason());

        $requests = [];
        $base = new Chat(new OpenAi('key', $this->client([$this->answer('length')], $requests)), new Model('model'));
        try {
            (new FinishReasonGuardChat(new RetryChat($base, retryOnGuard: true)))->chat('Hi');
            $this->fail('Outer guard must reject without retrying.');
        } catch (GuardException $failure) {
            $this->assertSame(FinishReason::LENGTH, $failure->getResponse()->getResult()->getFinishReason());
            $this->assertNotNull($failure->getPrevious());
        }
    }

    public function testWholeChatRetryRejectsToolsBeforeAnyRequest(): void
    {
        $requests = [];
        $base = new Chat(new OpenAi('key', $this->client([], $requests)), new Model('model'));
        $this->expectException(InvalidArgumentException::class);
        (new RetryChat($base))->chat(new ChatRequest(new Conversation(['Hi']), tools: new ToolCollection()));
    }

    public function testMalformedResponseIsNormalizedAndNotRetried(): void
    {
        $requests = [];
        $base = new RetryProvider(new OpenAi('key', $this->client([new Response('{')], $requests)));
        try {
            (new Chat($base, new Model('model')))->chat('Hi');
            $this->fail('Malformed JSON must fail.');
        } catch (ProviderException $failure) {
            $this->assertSame(FailureKind::INVALID_RESPONSE, $failure->getKind());
            $this->assertNotNull($failure->getPrevious());
        }
    }

    public function testExhaustedRetriesDoNotSleepAfterLastAttempt(): void
    {
        $requests = [];
        $sleeps = [];
        $provider = new RetryProvider(new OpenAi('key', $this->client([new Response('', 503), new Response('', 503), new Response('', 503)], $requests)),
            new RetryPolicy(3, 10, 2, 100), new RetryRunner(static function (int $ms) use (&$sleeps): void { $sleeps[] = $ms; }));
        try {
            (new Chat($provider, new Model('model')))->chat('Hi');
            $this->fail('Retries must be bounded.');
        } catch (ProviderException $failure) {
            $this->assertSame([10, 20], $sleeps);
            $this->assertSame(503, $failure->getCode());
        }
    }

    public function testFailoverDoesNotMaskAuthenticationErrors(): void
    {
        $first = [];
        $second = [];
        $provider = new FailoverProvider([
            new OpenAi('key', $this->client([new Response('', 403)], $first)),
            new OpenAi('key', $this->client([], $second)),
        ]);
        $this->expectException(ProviderException::class);
        (new Chat($provider, new Model('model')))->chat('Hi');
    }

    public function testRetryAroundFailoverRepeatsTheOrderedRouteList(): void
    {
        $first = [];
        $second = [];
        $provider = new RetryProvider(new FailoverProvider([
            new OpenAi('key', $this->client([new Response('', 503), $this->answer()], $first), id: 'first'),
            new OpenAi('key', $this->client([new Response('', 503)], $second), id: 'second'),
        ]), new RetryPolicy(initialDelayMs: 0));
        $response = (new Chat($provider, new Model('model')))->chat('Hi');
        $this->assertSame('first', $response->getResult()->getProviderId());
        $this->assertCount(2, $first);
        $this->assertCount(1, $second);
    }

    public function testGuardRejectionsAreNotRetriedByDefault(): void
    {
        $requests = [];
        $base = new Chat(new OpenAi('key', $this->client([$this->answer('length')], $requests)), new Model('model'));
        $this->expectException(GuardException::class);
        (new RetryChat(new FinishReasonGuardChat($base)))->chat('Hi');
    }

    /** @param list<ResponseInterface> $responses */
    private function client(array $responses, array &$requests): ClientInterface
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(count($responses)))->method('sendRequest')->willReturnCallback(
            static function (RequestInterface $request) use (&$responses, &$requests): ResponseInterface {
                $requests[] = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                return array_shift($responses);
            },
        );
        return $client;
    }

    private function answer(string $reason = 'stop'): ResponseInterface
    {
        return new Response(json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Done'], 'finish_reason' => $reason]]], JSON_THROW_ON_ERROR));
    }

    private function toolTurn(): ResponseInterface
    {
        return new Response('{"choices":[{"message":{"role":"assistant","content":null,"tool_calls":[{"id":"one","type":"function","function":{"name":"work","arguments":"{}"}}]},"finish_reason":"tool_calls"}]}');
    }
}
