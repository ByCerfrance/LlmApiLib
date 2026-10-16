<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Chat;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\FinishReason;
use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\Message\SystemMessage;
use ByCerfrance\LlmApiLib\Completion\Message\UserMessage;
use ByCerfrance\LlmApiLib\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\OpenAi;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

#[CoversClass(Chat::class)]
#[CoversClass(ChatRequest::class)]
#[CoversClass(ChatResponse::class)]
#[CoversClass(Conversation::class)]
#[CoversClass(Model::class)]
#[CoversClass(ChatResult::class)]
#[CoversClass(OpenAi::class)]
#[CoversClass(ProviderChatRequest::class)]
#[CoversClass(TokenUsage::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(FinishReason::class)]
#[UsesClass(Message::class)]
#[UsesClass(PayloadBuilder::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(SystemMessage::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(UserMessage::class)]
class ChatTest extends TestCase
{
    public function testTwoExchangesAndIndependentBranchPreserveSystemAndOptions(): void
    {
        $requests = [];
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(3))->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request) use (&$requests): ResponseInterface {
                $this->assertSame('POST', $request->getMethod());
                $this->assertSame('https://api.openai.com/v1/chat/completions', (string) $request->getUri());
                $this->assertSame('Bearer test-key', $request->getHeaderLine('Authorization'));
                $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
                $requests[] = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);

                return $this->response('Answer ' . count($requests));
            },
        );
        $provider = new OpenAi('test-key', $client, id: 'primary');
        $chat = new Chat($provider, new Model('requested-model'));
        $request = new ChatRequest(
            new Conversation([new SystemMessage('Reply in French.'), 'Explain crop rotation.']),
            maxTokens: 250,
            temperature: 0,
            topP: 0,
        );
        $first = $chat->chat($request);
        $second = $chat->chat($request->withConversation($first->toConversation()->withNewMessage('Give an example.')));
        $branch = $chat->chat($request->withConversation($first->toConversation()->withNewMessage('Summarize instead.')));

        $this->assertCount(2, $request->getConversation());
        $this->assertCount(3, $first->toConversation());
        $this->assertCount(5, $second->toConversation());
        $this->assertCount(5, $branch->toConversation());
        $this->assertSame(['system', 'user', 'assistant', 'user'], array_column($requests[1]['messages'], 'role'));
        $this->assertSame(['Reply in French.', 'Explain crop rotation.', 'Answer 1', 'Give an example.'], array_column($requests[1]['messages'], 'content'));
        $this->assertSame('Summarize instead.', $requests[2]['messages'][3]['content']);
        $this->assertSame('Answer 1', $requests[2]['messages'][2]['content']);
        foreach ($requests as $payload) {
            $this->assertSame('requested-model', $payload['model']);
            $this->assertSame(250, $payload['max_completion_tokens']);
            $this->assertSame(0, $payload['temperature']);
            $this->assertSame(0, $payload['top_p']);
            $this->assertFalse($payload['stream']);
        }
        $this->assertSame('primary', $provider->getId());
        $this->assertSame('primary', $first->getResult()->getProviderId());
        $this->assertSame('actual-model', $first->getResult()->getModelName());
        $this->assertSame('req-test', $first->getResult()->getRequestId());
        $this->assertSame(FinishReason::STOP, $first->getResult()->getFinishReason());
        $this->assertSame(10, $first->getUsage()->getPromptTokens());
        $this->assertSame(4, $first->getUsage()->getCompletionTokens());
        $this->assertSame(14, $first->getUsage()->getTotalTokens());
        $this->assertSame(0, $first->getUsage()->getCachedTokens());
        $this->assertSame(14, $second->getUsage()->getTotalTokens());
        $this->assertSame(14, $first->getUsage()->getTotalTokens());
    }

    public function testResetOmitsSettingsWithoutMutatingOriginal(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request): ResponseInterface {
                $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertArrayNotHasKey('temperature', $body);
                $this->assertArrayNotHasKey('top_p', $body);
                $this->assertArrayNotHasKey('max_completion_tokens', $body);

                return $this->response();
            },
        );
        $original = new ChatRequest(new Conversation(['Hi']), maxTokens: 100, temperature: 1, topP: 1);
        $reset = $original->withTemperature(null)->withTopP(null)->withMaxTokens(null);
        (new Chat(new OpenAi('key', $client), new Model('model')))->chat($reset);
        $this->assertSame(1, $original->getTemperature());
        $this->assertSame(1, $original->getTopP());
        $this->assertSame(100, $original->getMaxTokens());
    }

    public function testRequestOverridesDefaultsAndResetInheritsDefaults(): void
    {
        $payloads = [];
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(2))->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request) use (&$payloads): ResponseInterface {
                $payloads[] = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);

                return $this->response();
            },
        );
        $chat = new Chat(new OpenAi('key', $client), new Model('model'), maxTokens: 500, temperature: 0.8, topP: 0.9);
        $request = new ChatRequest(new Conversation(['Hi']), temperature: 0, topP: 0);
        $chat->chat($request);
        $chat->chat($request->withTemperature(null)->withTopP(null));
        $this->assertSame(0, $payloads[0]['temperature']);
        $this->assertSame(0, $payloads[0]['top_p']);
        $this->assertSame(500, $payloads[0]['max_completion_tokens']);
        $this->assertSame(0.8, $payloads[1]['temperature']);
        $this->assertSame(0.9, $payloads[1]['top_p']);
    }

    public function testStringRequestAndUnknownUsage(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request): ResponseInterface {
                $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame([['content' => 'Hi', 'role' => 'user']], $body['messages']);

                return new Response('{"choices":[{"message":{"role":"assistant","content":"Hello"}}]}');
            },
        );
        $response = (new Chat(new OpenAi('key', $client), new Model('model')))->chat('Hi');
        $this->assertCount(2, $response->toConversation());
        $this->assertNull($response->getUsage()->getPromptTokens());
        $this->assertNull($response->getUsage()->getCompletionTokens());
        $this->assertNull($response->getUsage()->getTotalTokens());
        $this->assertNull($response->getUsage()->getCachedTokens());
        $this->assertNull($response->getResult()->getModelName());
        $this->assertNull($response->getResult()->getRequestId());
        $this->assertNull($response->getResult()->getFinishReason());
    }

    /** @return iterable<string, array{string, int}> */
    public static function invalidResponses(): iterable
    {
        yield 'HTTP failure' => ['{"error":"secret body"}', 429];
        yield 'invalid JSON' => ['not JSON', 200];
        yield 'scalar JSON' => ['true', 200];
        yield 'missing choices' => ['{}', 200];
        yield 'empty choices' => ['{"choices":[]}', 200];
        yield 'invalid content' => ['{"choices":[{"message":{"role":"assistant","content":42}}]}', 200];
        yield 'unexpected tool call' => ['{"choices":[{"message":{"role":"assistant","content":"text","tool_calls":[{}]}}]}', 200];
        yield 'negative tokens' => ['{"choices":[{"message":{"role":"assistant","content":"Hi"}}],"usage":{"prompt_tokens":-1}}', 200];
        yield 'invalid usage' => ['{"choices":[{"message":{"role":"assistant","content":"Hi"}}],"usage":"bad"}', 200];
    }

    #[DataProvider('invalidResponses')]
    public function testProviderFailuresDoNotBecomeSuccessfulResponses(string $body, int $status): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturn(new Response($body, $status));
        $this->expectException(RuntimeException::class);
        (new Chat(new OpenAi('key', $client), new Model('model')))->chat('Hi');
    }

    public function testReservedModelCannotBeOverriddenByExtensions(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $this->expectException(InvalidArgumentException::class);
        new OpenAi('key', $client, extraBody: ['model' => 'other-model']);
    }

    public function testConfiguredOptionCannotBeOverriddenByExtensions(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $chat = new Chat(new OpenAi('key', $client, extraBody: ['temperature' => 1]), new Model('model'));
        $this->expectException(InvalidArgumentException::class);
        $chat->chat(new ChatRequest(new Conversation(['Hi']), temperature: 0));
    }

    public function testEmptyConversationIsRejectedBeforeHttp(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $this->expectException(InvalidArgumentException::class);
        (new Chat(new OpenAi('key', $client), new Model('model')))->chat(new ChatRequest(new Conversation()));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidExtensions(): iterable
    {
        yield 'negative temperature' => [['temperature' => -1]];
        yield 'string temperature' => [['temperature' => 'warm']];
        yield 'invalid top p' => [['top_p' => 2]];
        yield 'invalid maximum' => [['max_completion_tokens' => 0]];
        yield 'model override' => [['model' => 'other']];
        yield 'history override' => [['messages' => []]];
        yield 'streaming' => [['stream' => true]];
        yield 'multiple choices' => [['n' => 2]];
    }

    #[DataProvider('invalidExtensions')]
    public function testExtensionsCannotBypassValidation(array $extraBody): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $this->expectException(InvalidArgumentException::class);
        (new Chat(new OpenAi('key', $client, extraBody: $extraBody), new Model('model')))->chat('Hi');
    }

    public function testValidExtensionPreservesNestedFields(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request): ResponseInterface {
                $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(0, $body['temperature']);
                $this->assertSame(['temperature' => 'application metadata'], $body['metadata']);

                return $this->response();
            },
        );
        $provider = new OpenAi('key', $client, extraBody: ['temperature' => 0, 'metadata' => ['temperature' => 'application metadata']]);
        (new Chat($provider, new Model('model')))->chat('Hi');
    }

    public function testTransportFailurePreservesCauseWithoutExposingItsMessage(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $failure = new class('sensitive transport details') extends RuntimeException implements NetworkExceptionInterface {
            #[\Override]
            public function getRequest(): RequestInterface
            {
                return new \Berlioz\Http\Message\Request('POST', 'https://api.openai.com/v1/chat/completions');
            }
        };
        $client->expects($this->once())->method('sendRequest')->willThrowException($failure);
        try {
            (new Chat(new OpenAi('key', $client), new Model('model')))->chat('Hi');
            $this->fail('A transport failure must not produce a response.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception->getPrevious());
            $this->assertStringNotContainsString('sensitive', $exception->getMessage());
        }
    }

    public function testHttpFailureDoesNotExposeResponseBody(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturn(new Response('private provider details', 401));
        try {
            (new Chat(new OpenAi('key', $client), new Model('model')))->chat('Hi');
            $this->fail('An HTTP failure must not produce a response.');
        } catch (RuntimeException $exception) {
            $this->assertSame(401, $exception->getCode());
            $this->assertStringNotContainsString('private', $exception->getMessage());
        }
    }

    private function response(string $text = 'Hello'): ResponseInterface
    {
        return new Response(
            json_encode([
                'model' => 'actual-model',
                'choices' => [['message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 4, 'total_tokens' => 14, 'prompt_tokens_details' => ['cached_tokens' => 0]],
            ], JSON_THROW_ON_ERROR),
            headers: ['x-request-id' => 'req-test'],
        );
    }
}
