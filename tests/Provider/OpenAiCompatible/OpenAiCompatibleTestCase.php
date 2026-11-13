<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider\OpenAiCompatible;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\SystemMessage;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Conformance checks shared by every OpenAI-compatible provider.
 */
abstract class OpenAiCompatibleTestCase extends TestCase
{
    abstract protected function createProvider(ClientInterface $client): AbstractOpenAiCompatible;

    abstract protected function getEndpoint(): string;

    abstract protected function getExpectedId(): string;

    protected function getTokenField(): string
    {
        return 'max_completion_tokens';
    }

    public function testTwoTurnConversation(): void
    {
        $endpoint = $this->getEndpoint();
        $tokenField = $this->getTokenField();
        $turn = 0;
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(2))->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request) use ($endpoint, $tokenField, &$turn): ResponseInterface {
                $this->assertSame($endpoint, (string) $request->getUri());
                $this->assertSame('Bearer key', $request->getHeaderLine('Authorization'));
                $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame('model', $body['model']);
                $this->assertSame(200, $body[$tokenField]);
                $this->assertArrayNotHasKey('temperature', $body);
                $this->assertArrayNotHasKey('top_p', $body);
                $this->assertArrayNotHasKey($tokenField === 'max_tokens' ? 'max_completion_tokens' : 'max_tokens', $body);
                $this->assertCount($turn === 0 ? 2 : 4, $body['messages']);
                $this->assertSame('system', $body['messages'][0]['role']);
                if ($turn > 0) {
                    $this->assertSame('Answer', $body['messages'][2]['content']);
                }
                $turn++;

                return $this->response();
            },
        );
        $provider = $this->createProvider($client);
        $this->assertSame($this->getExpectedId(), $provider->getId());
        $chat = new Chat($provider, new Model('model'));
        $request = new ChatRequest(new Conversation([new SystemMessage('Be concise.'), 'Hi']), maxTokens: 200);
        $first = $chat->chat($request);
        $second = $chat->chat($request->withConversation($first->toConversation()->withNewMessage('Continue')));
        $this->assertSame('actual', $second->getResult()->getModelName());
        $this->assertSame($provider->getId(), $second->getResult()->getProviderId());
        $this->assertSame(3, $second->getUsage()->getTotalTokens());
    }

    protected function response(): ResponseInterface
    {
        return new Response('{"model":"actual","choices":[{"message":{"role":"assistant","content":"Answer"},"finish_reason":"stop"}],"usage":{"prompt_tokens":2,"completion_tokens":1,"total_tokens":3}}');
    }

    public function testReasoningIsAlreadyIncludedInOutputAndCacheIsASubsetOfInput(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturn(new Response(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Answer'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 2124, 'completion_tokens' => 241, 'total_tokens' => 2365,
                'prompt_tokens_details' => ['cached_tokens' => 1000],
                'completion_tokens_details' => ['reasoning_tokens' => 196]],
        ], JSON_THROW_ON_ERROR)));
        $usage = (new Chat($this->createProvider($client), new Model('model')))->chat('Hi')->getUsage();
        $this->assertSame(2124, $usage->getPromptTokens());
        $this->assertSame(241, $usage->getCompletionTokens());
        $this->assertSame(2365, $usage->getTotalTokens());
        $this->assertSame(1000, $usage->getCachedTokens());
    }

    public function testOmittedCacheDetailsRemainUnknown(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturn($this->response());
        $usage = (new Chat($this->createProvider($client), new Model('model')))->chat('Hi')->getUsage();
        $this->assertSame(2, $usage->getPromptTokens());
        $this->assertSame(1, $usage->getCompletionTokens());
        $this->assertNull($usage->getCachedTokens());
    }
}
