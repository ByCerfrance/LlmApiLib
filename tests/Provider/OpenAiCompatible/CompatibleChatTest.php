<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider\OpenAiCompatible;

use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\SystemMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Chat\ReasoningEffort;
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
use ByCerfrance\LlmApiLib\Provider\Google\Google;
use ByCerfrance\LlmApiLib\Provider\Google\GoogleChatBuilder;
use ByCerfrance\LlmApiLib\Provider\Mistral\Mistral;
use ByCerfrance\LlmApiLib\Provider\Mistral\MistralChatBuilder;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAi;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\ChatContentBuilder;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\Generic;
use ByCerfrance\LlmApiLib\Provider\Ovh\Ovh;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Tool\InvocationRegistry;
use ByCerfrance\LlmApiLib\Tool\JsonSchemaArgumentsValidator;
use ByCerfrance\LlmApiLib\Tool\ToolErrorPolicy;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(AbstractOpenAiCompatible::class)]
#[CoversClass(OpenAi::class)]
#[CoversClass(Ovh::class)]
#[CoversClass(Google::class)]
#[CoversClass(Mistral::class)]
#[CoversClass(Generic::class)]
#[CoversClass(MistralChatBuilder::class)]
#[CoversClass(GoogleChatBuilder::class)]
#[CoversClass(ChatPayload::class)]
#[UsesClass(Chat::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(Model::class)]
#[UsesClass(ModelChatBuilder::class)]
#[UsesClass(ChatContentBuilder::class)]
#[UsesClass(ParameterPolicy::class)]
#[UsesClass(Capability::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(TokenUsage::class)]
#[UsesClass(PayloadBuilder::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(SystemMessage::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(FinishReason::class)]
#[UsesClass(ReasoningEffort::class)]
#[UsesClass(ToolChoice::class)]
#[UsesClass(InvocationRegistry::class)]
#[UsesClass(JsonSchemaArgumentsValidator::class)]
#[UsesClass(ToolErrorPolicy::class)]
#[UsesClass(ToolExecutor::class)]
class CompatibleChatTest extends TestCase
{
    /** @return iterable<string, array{class-string<AbstractOpenAiCompatible>, string, string}> */
    public static function providers(): iterable
    {
        yield 'OpenAI' => [OpenAi::class, 'https://api.openai.com/v1/chat/completions', 'max_completion_tokens'];
        yield 'OVH' => [Ovh::class, 'https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/chat/completions', 'max_completion_tokens'];
        yield 'Google' => [Google::class, 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', 'max_completion_tokens'];
        yield 'Mistral' => [Mistral::class, 'https://api.mistral.ai/v1/chat/completions', 'max_tokens'];
        yield 'Generic' => [Generic::class, 'https://example.test/custom/completions?deployment=one', 'max_completion_tokens'];
    }

    /** @param class-string<AbstractOpenAiCompatible> $providerClass */
    #[DataProvider('providers')]
    public function testTwoTurnsAcrossProviders(string $providerClass, string $endpoint, string $tokenField): void
    {
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
        $provider = $providerClass === Generic::class
            ? new Generic(Uri::createFromString($endpoint), 'key', $client)
            : new $providerClass('key', $client);
        $this->assertSame(substr(strrchr($providerClass, '\\'), 1), $provider->getId());
        $chat = new Chat($provider, new Model('model'));
        $request = new ChatRequest(new Conversation([new SystemMessage('Be concise.'), 'Hi']), maxTokens: 200);
        $first = $chat->chat($request);
        $second = $chat->chat($request->withConversation($first->toConversation()->withNewMessage('Continue')));
        $this->assertSame('actual', $second->getResult()->getModelName());
        $this->assertSame($provider->getId(), $second->getResult()->getProviderId());
        $this->assertSame(3, $second->getUsage()->getTotalTokens());
    }

    public function testGoogleKeepsExplicitOptionsAndNestedSchemas(): void
    {
        $schema = ['type' => 'object', 'properties' => ['temperature' => ['type' => 'number'], 'top_p' => ['type' => 'number']]];
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request) use ($schema): ResponseInterface {
                $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(0, $body['temperature']);
                $this->assertSame(0, $body['top_p']);
                $this->assertSame('low', $body['reasoning_effort']);
                $this->assertSame($schema, $body['response_format']['json_schema']['schema']);

                return $this->response();
            },
        );
        $provider = new Google('key', $client, extraBody: [
            'top_p' => 0,
            'reasoning_effort' => 'low',
            'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'sample', 'schema' => $schema]],
        ]);
        (new Chat($provider, new Model('unknown-custom-model')))->chat(new ChatRequest(new Conversation(['Hi']), temperature: 0));
    }

    public function testMistralRootMappingsDoNotRewriteSchemaProperties(): void
    {
        $schema = ['max_completion_tokens' => ['type' => 'integer'], 'service_tier' => ['type' => 'string']];
        $builder = new PayloadBuilder([new MistralChatBuilder()]);
        $result = $builder->build(new ChatPayload([
            'max_completion_tokens' => 12,
            'reasoning_effort' => ReasoningEffort::HIGH,
            'tool_choice' => ToolChoice::REQUIRED,
            'response_format' => ['schema' => $schema],
        ]));
        $this->assertSame(12, $result['max_tokens']);
        $this->assertArrayNotHasKey('max_completion_tokens', $result);
        $this->assertSame('high', $result['reasoning_effort']);
        $this->assertSame('any', $result['tool_choice']);
        $this->assertSame($schema, $result['response_format']['schema']);
    }

    public function testGoogleRejectsCompetingReasoningOptionsBeforeHttp(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $provider = new Google('key', $client, extraBody: [
            'reasoning_effort' => 'low',
            'extra_body' => ['google' => ['thinking_config' => ['thinking_level' => 'high']]],
        ]);
        $this->expectException(InvalidArgumentException::class);
        (new Chat($provider, new Model('model')))->chat('Hi');
    }

    public function testGoogleAllowsThoughtSummariesWithReasoningEffort(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request): ResponseInterface {
                $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame('low', $body['reasoning_effort']);
                $this->assertTrue($body['extra_body']['google']['thinking_config']['include_thoughts']);

                return $this->response();
            },
        );
        $provider = new Google('key', $client, extraBody: [
            'reasoning_effort' => 'low',
            'extra_body' => ['google' => ['thinking_config' => ['include_thoughts' => true]]],
        ]);
        (new Chat($provider, new Model('model')))->chat('Hi');
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function incompatibleMistralOptions(): iterable
    {
        yield 'service tier' => [['service_tier' => 'flex']];
        yield 'reasoning degradation' => [['reasoning_effort' => 'medium']];
        yield 'duplicate maximum' => [['max_completion_tokens' => 20, 'max_tokens' => 10]];
        yield 'invalid native maximum' => [['max_tokens' => -1]];
    }

    #[DataProvider('incompatibleMistralOptions')]
    public function testMistralRejectsIncompatibleExplicitOptionsBeforeHttp(array $options): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $this->expectException(InvalidArgumentException::class);
        (new Chat(new Mistral('key', $client, extraBody: $options), new Model('model')))->chat('Hi');
    }

    public function testCustomProviderIdAndStringEndpoint(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request): ResponseInterface {
                $this->assertSame('https://example.test/chat', (string) $request->getUri());

                return $this->response();
            },
        );
        $result = (new Chat(new Generic('https://example.test/chat', 'key', $client, id: 'custom'), new Model('model')))->chat('Hi');
        $this->assertSame('custom', $result->getResult()->getProviderId());
    }

    private function response(): ResponseInterface
    {
        return new Response('{"model":"actual","choices":[{"message":{"role":"assistant","content":"Answer"},"finish_reason":"stop"}],"usage":{"prompt_tokens":2,"completion_tokens":1,"total_tokens":3}}');
    }
}
