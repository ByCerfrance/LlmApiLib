<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider\Google;

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
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\ModelChatBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\Google\Google;
use ByCerfrance\LlmApiLib\Provider\Google\GoogleChatBuilder;
use ByCerfrance\LlmApiLib\Provider\JsonTransport;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\ChatContentBuilder;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Tests\Provider\OpenAiCompatible\OpenAiCompatibleTestCase;
use ByCerfrance\LlmApiLib\Tool\JsonSchemaArgumentsValidator;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(Google::class)]
#[CoversClass(GoogleChatBuilder::class)]
#[UsesClass(Chat::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(SystemMessage::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(FinishReason::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(Model::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(ChatPayload::class)]
#[UsesClass(ModelChatBuilder::class)]
#[UsesClass(PayloadBuilder::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(JsonTransport::class)]
#[UsesClass(ChatContentBuilder::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(JsonSchemaArgumentsValidator::class)]
#[UsesClass(ToolExecutor::class)]
#[UsesClass(TokenUsage::class)]
class GoogleTest extends OpenAiCompatibleTestCase
{
    protected function createProvider(ClientInterface $client): AbstractOpenAiCompatible
    {
        return new Google('key', $client);
    }

    protected function getEndpoint(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
    }

    protected function getExpectedId(): string
    {
        return 'Google';
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
}
