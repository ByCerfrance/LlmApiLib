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
use ByCerfrance\LlmApiLib\Chat\ReasoningEffort;
use ByCerfrance\LlmApiLib\Chat\ResponseFormat\JsonObjectFormat;
use ByCerfrance\LlmApiLib\Chat\ResponseFormat\JsonSchemaFormat;
use ByCerfrance\LlmApiLib\Chat\ServiceTier;
use ByCerfrance\LlmApiLib\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Content\DocumentUrlContent;
use ByCerfrance\LlmApiLib\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Content\InputAudioContent;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Model\ParameterPolicy;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\ModelChatBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\JsonTransport;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAi;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAiChatBuilder;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\ChatContentBuilder;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\Generic;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Tool\InvocationRegistry;
use ByCerfrance\LlmApiLib\Tool\JsonSchemaArgumentsValidator;
use ByCerfrance\LlmApiLib\Tool\ToolErrorPolicy;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(ChatRequest::class)]
#[CoversClass(ModelChatBuilder::class)]
#[CoversClass(ChatContentBuilder::class)]
#[UsesClass(Chat::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(Model::class)]
#[UsesClass(Capability::class)]
#[UsesClass(ParameterPolicy::class)]
#[UsesClass(AbstractOpenAiCompatible::class)]
#[UsesClass(OpenAi::class)]
#[UsesClass(Generic::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(TokenUsage::class)]
#[UsesClass(PayloadBuilder::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(ChatPayload::class)]
#[UsesClass(Message::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(FinishReason::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(ArrayContent::class)]
#[UsesClass(ImageUrlContent::class)]
#[UsesClass(DocumentUrlContent::class)]
#[UsesClass(InputAudioContent::class)]
#[UsesClass(JsonSchemaFormat::class)]
#[UsesClass(JsonObjectFormat::class)]
#[UsesClass(ReasoningEffort::class)]
#[UsesClass(ServiceTier::class)]
#[UsesClass(InvocationRegistry::class)]
#[UsesClass(JsonSchemaArgumentsValidator::class)]
#[UsesClass(ToolErrorPolicy::class)]
#[UsesClass(ToolExecutor::class)]
#[UsesClass(JsonTransport::class)]
#[UsesClass(OpenAiChatBuilder::class)]
class ModelChatTest extends TestCase
{
    public function testMultimodalInputsAndStructuredOptionsSurviveContinuation(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(2))->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request): ResponseInterface {
                $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(['text', 'image_url', 'input_audio', 'document_url'], array_column($body['messages'][0]['content'], 'type'));
                $this->assertSame('json_schema', $body['response_format']['type']);
                $this->assertTrue($body['response_format']['json_schema']['strict']);
                $this->assertSame('high', $body['reasoning_effort']);
                $this->assertSame('flex', $body['service_tier']);
                $this->assertSame(0, $body['seed']);

                return $this->response();
            },
        );
        $model = new Model('custom', capabilities: [Capability::TEXT, Capability::VISION, Capability::AUDIO, Capability::DOCUMENT, Capability::JSON_SCHEMA, Capability::REASONING]);
        $chat = new Chat(new Generic('https://example.test/chat', 'key', $client), $model);
        $request = new ChatRequest(new Conversation([new UserMessage(new ArrayContent(
            'Analyze these inputs.',
            new ImageUrlContent('https://example.test/image.png'),
            new InputAudioContent('YXVkaW8=', 'wav'),
            new DocumentUrlContent('https://example.test/document.pdf'),
        ))]));
        $configured = $request
            ->withResponseFormat(new JsonSchemaFormat('answer', ['type' => 'object'], strict: true))
            ->withReasoningEffort(ReasoningEffort::HIGH)
            ->withServiceTier(ServiceTier::FLEX)
            ->withSeed(0);
        $first = $chat->chat($configured);
        $chat->chat($configured->withConversation($first->toConversation()->withNewMessage('Continue.')));
        $this->assertNull($request->getResponseFormat());
        $this->assertNull($request->getReasoningEffort());
        $this->assertNull($request->getServiceTier());
        $this->assertNull($request->getSeed());
    }

    public function testKnownMissingVisionIsRejectedBeforeHttp(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $chat = new Chat(new OpenAi('key', $client), new Model('text-only', capabilities: [Capability::TEXT]));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('vision');
        $chat->chat(new ChatRequest(new Conversation([new UserMessage(new ImageUrlContent('https://example.test/image.png'))])));
    }

    public function testUnknownCapabilitiesAllowSingleImageContentAsAPartList(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request): ResponseInterface {
                $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame('image_url', $body['messages'][0]['content'][0]['type']);

                return $this->response();
            },
        );
        (new Chat(new OpenAi('key', $client), new Model('unknown')))->chat(
            new ChatRequest(new Conversation([new UserMessage(new ImageUrlContent('https://example.test/image.png'))])),
        );
    }

    public function testUnsupportedSamplingInjectedThroughExtensionsIsRejected(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $chat = new Chat(new OpenAi('key', $client, extraBody: ['temperature' => 0]), new Model('fixed', tunable: false));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('temperature');
        $chat->chat('Hi');
    }

    public function testPermissivePolicyReportsOmissionsWithoutTouchingSchema(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('debug');
        $logger->expects($this->exactly(2))->method('warning')->with(
            'Unsupported generation parameter omitted.',
            $this->callback(static fn(array $context): bool => $context['model'] === 'fixed'
                && in_array($context['parameter'], ['temperature', 'response_format.json_schema.strict'], true)),
        );
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request): ResponseInterface {
                $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertArrayNotHasKey('temperature', $body);
                $this->assertArrayNotHasKey('strict', $body['response_format']['json_schema']);
                $this->assertSame(['type' => 'number'], $body['response_format']['json_schema']['schema']['properties']['temperature']);

                return $this->response();
            },
        );
        $provider = new OpenAi('key', $client, logger: $logger, parameterPolicy: ParameterPolicy::OMIT_UNSUPPORTED);
        $model = new Model('fixed', tunable: false, unsupportedParameters: ['response_format.json_schema.strict']);
        (new Chat($provider, $model))->chat(new ChatRequest(new Conversation(['Hi']), temperature: 0,
            responseFormat: new JsonSchemaFormat('answer', ['type' => 'object', 'properties' => ['temperature' => ['type' => 'number']]], strict: true)));
    }

    public function testOutputLimitIsCheckedAfterExtensions(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $chat = new Chat(new OpenAi('key', $client, extraBody: ['max_completion_tokens' => 200]), new Model('small', maxOutputTokens: 100));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('100-token limit');
        $chat->chat('Hi');
    }

    public function testUnsupportedAllowedParameterValueIsNotDowngraded(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $model = new Model('high-only', parameterValues: ['reasoning_effort' => ['high']]);
        $this->expectException(InvalidArgumentException::class);
        (new Chat(new OpenAi('key', $client), $model))->chat(new ChatRequest(new Conversation(['Hi']), reasoningEffort: ReasoningEffort::LOW));
    }

    public function testFormatCapabilityCannotBeBypassedThroughExtensions(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $provider = new OpenAi('key', $client, extraBody: ['response_format' => ['type' => 'json_object']]);
        $this->expectException(InvalidArgumentException::class);
        (new Chat($provider, new Model('text-only', capabilities: [Capability::TEXT])))->chat('Hi');
    }

    public function testJsonObjectOutputUsesExistingFormat(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request): ResponseInterface {
                $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(['type' => 'json_object'], $body['response_format']);

                return $this->response();
            },
        );
        $response = (new Chat(new OpenAi('key', $client), new Model('json', capabilities: [Capability::TEXT, Capability::JSON_OUTPUT])))->chat(
            new ChatRequest(new Conversation(['Respond with JSON.']), responseFormat: new JsonObjectFormat()),
        );
        $this->assertSame('{"ok":true}', (string) $response->getResult()->getMessage()->getContent());
    }

    public function testOpenAiInlinePdfUsesFileInput(): void
    {
        $data = 'data:application/pdf;base64,JVBERi0x';
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request) use ($data): ResponseInterface {
                $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(['type' => 'file', 'file' => ['filename' => 'report.pdf', 'file_data' => $data]], $body['messages'][0]['content'][0]);

                return $this->response();
            },
        );
        (new Chat(new OpenAi('key', $client), new Model('pdf')))->chat(new ChatRequest(
            new Conversation([new UserMessage(new DocumentUrlContent($data, name: 'report.pdf'))]),
        ));
    }

    public function testOpenAiRemoteDocumentDoesNotSendUnsupportedWireFormat(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $this->expectException(InvalidArgumentException::class);
        (new Chat(new OpenAi('key', $client), new Model('pdf')))->chat(new ChatRequest(
            new Conversation([new UserMessage(new DocumentUrlContent('https://example.test/document.pdf'))]),
        ));
    }

    private function response(): ResponseInterface
    {
        return new Response('{"choices":[{"message":{"role":"assistant","content":"{\\"ok\\":true}"},"finish_reason":"stop"}]}');
    }
}
