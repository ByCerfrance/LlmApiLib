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
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Chat\Guard\FinishReasonGuardChat;
use ByCerfrance\LlmApiLib\Chat\Guard\GuardChat;
use ByCerfrance\LlmApiLib\Chat\Guard\GuardException;
use ByCerfrance\LlmApiLib\Chat\RetryChat;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\FailoverProvider;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\ModelProvider;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Provider\RetryProvider;
use ByCerfrance\LlmApiLib\Resilience\RetryPolicy;
use ByCerfrance\LlmApiLib\Resilience\RetryRunner;
use ByCerfrance\LlmApiLib\Tests\Fixture\ScriptedChatProvider;
use ByCerfrance\LlmApiLib\Tool\AbstractTool;
use ByCerfrance\LlmApiLib\Tool\InvocationRegistry;
use ByCerfrance\LlmApiLib\Tool\JsonSchemaArgumentsValidator;
use ByCerfrance\LlmApiLib\Tool\Tool;
use ByCerfrance\LlmApiLib\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Tool\ToolSchema;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetryProvider::class)]
#[CoversClass(FailoverProvider::class)]
#[CoversClass(ModelProvider::class)]
#[CoversClass(RetryPolicy::class)]
#[CoversClass(RetryRunner::class)]
#[CoversClass(RetryChat::class)]
#[CoversClass(GuardChat::class)]
#[CoversClass(FinishReasonGuardChat::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(Model::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolResult::class)]
#[UsesClass(Chat::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(JsonSchemaArgumentsValidator::class)]
#[UsesClass(ToolCollection::class)]
#[UsesClass(ToolExecutor::class)]
#[UsesClass(ProviderException::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(TokenUsage::class)]
#[UsesClass(GuardException::class)]
#[UsesClass(AbstractTool::class)]
#[UsesClass(InvocationRegistry::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ToolSchema::class)]
class ResilienceTest extends TestCase
{
    public function testRetryAfterToolDoesNotReplayItsCallback(): void
    {
        $executions = 0;
        $sleeps = [];
        $provider = new ScriptedChatProvider([
            ScriptedChatProvider::toolTurn(new ToolCall('one', 'work', [])),
            ScriptedChatProvider::failure(FailureKind::UNAVAILABLE, 503, retryAfterMs: 2000),
            ScriptedChatProvider::answer(),
        ]);
        $retry = new RetryProvider($provider, runner: new RetryRunner(static function (int $ms) use (&$sleeps): void { $sleeps[] = $ms; }));
        $tool = new Tool('work', 'Work', [], function () use (&$executions): string { $executions++; return 'completed'; });
        $result = (new Chat($retry, new Model('model')))->chat(new ChatRequest(new Conversation(['Hi']), tools: new ToolCollection($tool)));
        $this->assertSame(1, $executions);
        $this->assertSame([2000], $sleeps);
        $this->assertSame($provider->requests[1], $provider->requests[2]);
        $this->assertCount(2, $result->getTurns());
    }

    public function testNonTransientFailuresAreNotRetried(): void
    {
        foreach ([FailureKind::AUTHENTICATION, FailureKind::INVALID_REQUEST, FailureKind::INVALID_RESPONSE, FailureKind::UNKNOWN] as $kind) {
            $failure = ScriptedChatProvider::failure($kind);
            $provider = new ScriptedChatProvider([$failure]);
            try {
                (new Chat(new RetryProvider($provider), new Model('model')))->chat('Hi');
                $this->fail('Expected a provider failure.');
            } catch (ProviderException $exception) {
                $this->assertSame($failure, $exception);
                $this->assertCount(1, $provider->requests);
            }
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
        $failure = ProviderException::fromResponse('test', new Response('', 429, headers: ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 60)]));
        $this->assertGreaterThanOrEqual(59000, $failure->getRetryAfterMs());
        $this->assertLessThanOrEqual(60000, $failure->getRetryAfterMs());
    }

    public function testFailoverChangesOnlyFailedTurnAndAllowsConfiguredModels(): void
    {
        $executions = 0;
        $first = new ScriptedChatProvider([
            ScriptedChatProvider::toolTurn(new ToolCall('one', 'work', [])),
            ScriptedChatProvider::failure(FailureKind::UNAVAILABLE, 503),
        ], 'first');
        $second = new ScriptedChatProvider([ScriptedChatProvider::answer(providerId: 'second')], 'second');
        $provider = new FailoverProvider([$first, new ModelProvider($second, new Model('fallback-model'))]);
        $tool = new Tool('work', 'Work', [], function () use (&$executions): string { $executions++; return 'completed'; });
        $response = (new Chat($provider, new Model('primary-model')))->chat(new ChatRequest(new Conversation(['Hi']), tools: new ToolCollection($tool)));
        $this->assertSame(1, $executions);
        $this->assertSame('primary-model', $first->requests[1]->getModel()->getName());
        $this->assertSame('fallback-model', $second->requests[0]->getModel()->getName());
        $this->assertSame($first->requests[1]->getRequest(), $second->requests[0]->getRequest());
        $this->assertSame('second', $response->getResult()->getProviderId());
    }

    public function testOpaqueContinuationRequiresPinnedProvider(): void
    {
        $first = new ScriptedChatProvider();
        $second = new ScriptedChatProvider();
        $provider = new FailoverProvider([$first, $second]);
        $request = new ProviderChatRequest(new Model('model'), new ChatRequest(new Conversation([
            new AssistantMessage(null, [new ToolCall('id', 'work', [], ['extra_content' => ['signature' => 'opaque']])]),
            new ToolResult('id', 'done'),
        ])));
        try {
            $provider->complete($request);
            $this->fail('Opaque continuation must not be transferred.');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $first->requests);
            $this->assertSame([], $second->requests);
        }
    }

    public function testGuardInsideRetryCanRegenerateButOutsideCannot(): void
    {
        $base = new Chat(new ScriptedChatProvider([
            ScriptedChatProvider::answer(finishReason: FinishReason::LENGTH),
            ScriptedChatProvider::answer(),
        ]), new Model('model'));
        $result = (new RetryChat(new FinishReasonGuardChat($base), new RetryPolicy(initialDelayMs: 0), retryOnGuard: true))->chat('Hi');
        $this->assertSame(FinishReason::STOP, $result->getResult()->getFinishReason());

        $base = new Chat(new ScriptedChatProvider([ScriptedChatProvider::answer(finishReason: FinishReason::LENGTH)]), new Model('model'));
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
        $provider = new ScriptedChatProvider();
        try {
            (new RetryChat(new Chat($provider, new Model('model'))))->chat(new ChatRequest(new Conversation(['Hi']), tools: new ToolCollection()));
            $this->fail('Whole-chat retries must reject tools.');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $provider->requests);
        }
    }

    public function testExhaustedRetriesDoNotSleepAfterLastAttempt(): void
    {
        $sleeps = [];
        $provider = new RetryProvider(new ScriptedChatProvider([
            ScriptedChatProvider::failure(FailureKind::UNAVAILABLE, 503),
            ScriptedChatProvider::failure(FailureKind::UNAVAILABLE, 503),
            ScriptedChatProvider::failure(FailureKind::UNAVAILABLE, 503),
        ]), new RetryPolicy(3, 10, 2, 100), new RetryRunner(static function (int $ms) use (&$sleeps): void { $sleeps[] = $ms; }));
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
        $second = new ScriptedChatProvider();
        $provider = new FailoverProvider([new ScriptedChatProvider([ScriptedChatProvider::failure(FailureKind::AUTHENTICATION, 403)]), $second]);
        try {
            (new Chat($provider, new Model('model')))->chat('Hi');
            $this->fail('Authentication failures must not fail over.');
        } catch (ProviderException $failure) {
            $this->assertSame(FailureKind::AUTHENTICATION, $failure->getKind());
            $this->assertSame([], $second->requests);
        }
    }

    public function testRetryAroundFailoverRepeatsTheOrderedRouteList(): void
    {
        $first = new ScriptedChatProvider([
            ScriptedChatProvider::failure(FailureKind::UNAVAILABLE, 503),
            ScriptedChatProvider::answer(providerId: 'first'),
        ], 'first');
        $second = new ScriptedChatProvider([ScriptedChatProvider::failure(FailureKind::UNAVAILABLE, 503)], 'second');
        $provider = new RetryProvider(new FailoverProvider([$first, $second]), new RetryPolicy(initialDelayMs: 0));
        $response = (new Chat($provider, new Model('model')))->chat('Hi');
        $this->assertSame('first', $response->getResult()->getProviderId());
        $this->assertCount(2, $first->requests);
        $this->assertCount(1, $second->requests);
    }

    public function testGuardRejectionsAreNotRetriedByDefault(): void
    {
        $provider = new ScriptedChatProvider([ScriptedChatProvider::answer(finishReason: FinishReason::LENGTH)]);
        try {
            (new RetryChat(new FinishReasonGuardChat(new Chat($provider, new Model('model')))))->chat('Hi');
            $this->fail('Guard rejections must propagate.');
        } catch (GuardException) {
            $this->assertCount(1, $provider->requests);
        }
    }
}
