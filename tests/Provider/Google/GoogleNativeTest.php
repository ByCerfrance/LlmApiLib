<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider\Google;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\ProviderAssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Chat\OutputModality;
use ByCerfrance\LlmApiLib\Chat\ServiceTier;
use ByCerfrance\LlmApiLib\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Content\ImageContent;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Image\ImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Image\ImageResponse;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Model\ParameterPolicy;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\ModelChatBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\Google\GoogleNative;
use ByCerfrance\LlmApiLib\Provider\ImageResult;
use ByCerfrance\LlmApiLib\Provider\JsonTransport;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\ChatContentBuilder;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\Generic;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Provider\ProviderImageRequest;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(GoogleNative::class)]
#[CoversClass(ProviderAssistantMessage::class)]
#[CoversClass(ImageContent::class)]
#[UsesClass(JsonTransport::class)]
#[UsesClass(Chat::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(OutputModality::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(Model::class)]
#[UsesClass(Capability::class)]
#[UsesClass(ParameterPolicy::class)]
#[UsesClass(AbstractOpenAiCompatible::class)]
#[UsesClass(Generic::class)]
#[UsesClass(ChatContentBuilder::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(TokenUsage::class)]
#[UsesClass(PayloadBuilder::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(ChatPayload::class)]
#[UsesClass(ModelChatBuilder::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(FinishReason::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(ArrayContent::class)]
#[UsesClass(ImageAsset::class)]
#[UsesClass(ServiceTier::class)]
#[UsesClass(ToolErrorPolicy::class)]
#[UsesClass(ToolExecutor::class)]
#[UsesClass(InvocationRegistry::class)]
#[UsesClass(JsonSchemaArgumentsValidator::class)]
#[UsesClass(AbstractTool::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolCollection::class)]
#[UsesClass(ToolResult::class)]
#[UsesClass(ToolSchema::class)]
#[UsesClass(ImageGenerator::class)]
#[UsesClass(ImageRequest::class)]
#[UsesClass(ImageResponse::class)]
#[UsesClass(ImageResult::class)]
#[UsesClass(ProviderImageRequest::class)]
#[UsesClass(ProviderException::class)]
class GoogleNativeTest extends TestCase
{
    public function testNativeImageGenerationEditAndTwoForksRetainSignaturesAndParts(): void
    {
        $requests = [];
        $client = $this->client([$this->native('initial'), $this->native('edited'), $this->native('branch-a'), $this->native('branch-b')], $requests);
        $chat = new Chat(new GoogleNative('key', $client), new Model('gemini-3.1-flash-image', [Capability::TEXT, Capability::VISION, Capability::IMAGE_OUTPUT]));
        $request = new ChatRequest(new Conversation(['Draw a farm']), outputModalities: [OutputModality::TEXT, OutputModality::IMAGE], imageConfig: ['aspectRatio' => '16:9', 'imageSize' => '1K']);
        $first = $chat->chat($request);
        $edited = $chat->chat($request->withConversation($first->toConversation()->withNewMessage('Make the barn red')));
        $a = $chat->chat($request->withConversation($edited->toConversation()->withNewMessage('Add a tree')));
        $b = $chat->chat($request->withConversation($edited->toConversation()->withNewMessage('Add a lake')));
        $this->assertCount(2, $first->toConversation());
        $this->assertCount(4, $edited->toConversation());
        $this->assertCount(6, $a->toConversation());
        $this->assertCount(6, $b->toConversation());
        $this->assertSame([1, 3, 5, 5], array_map(static fn(array $entry): int => count($entry['body']['contents']), $requests));
        $this->assertSame($requests[2]['body']['contents'][3], $requests[3]['body']['contents'][3]);
        $this->assertSame('signed-edited', $requests[3]['body']['contents'][3]['parts'][1]['thoughtSignature']);
        $this->assertSame(base64_encode('edited'), $requests[3]['body']['contents'][3]['parts'][1]['inlineData']['data']);
        $this->assertSame('image/png', $requests[1]['body']['contents'][1]['parts'][1]['inlineData']['mimeType']);
        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-image:generateContent', $requests[0]['uri']);
        $this->assertSame('key', $requests[0]['key']);
        $this->assertSame(['TEXT', 'IMAGE'], $requests[0]['body']['generationConfig']['responseModalities']);
        $this->assertSame('1K', $requests[0]['body']['generationConfig']['imageConfig']['imageSize']);
        $parts = iterator_to_array($first->getResult()->getMessage()->getContent(), false);
        $this->assertInstanceOf(TextContent::class, $parts[0]);
        $this->assertSame('initial', $parts[1]->image->getBytes());
        $this->assertSame(30, $first->getUsage()->getTotalTokens());
        $native = $first->getResult()->getMessage()->getNativeContent();
        $native['parts'][1]->thoughtSignature = 'mutated';
        $this->assertSame('signed-initial', $first->getResult()->getMessage()->getNativeContent()['parts'][1]->thoughtSignature);
    }

    public function testNativeHistoryCannotSwitchModelsOrProtocols(): void
    {
        $requests = [];
        $provider = new GoogleNative('key', $this->client([$this->native('initial')], $requests));
        $response = (new Chat($provider, new Model('gemini-3.1-flash-image')))->chat('Draw');
        $request = new ChatRequest($response->toConversation()->withNewMessage('Edit'));
        try {
            (new Chat($provider, new Model('other-model')))->chat($request);
            $this->fail('Cross-model continuation must fail.');
        } catch (InvalidArgumentException $failure) {
            $this->assertStringContainsString('different protocol', $failure->getMessage());
        }
        $empty = [];
        $this->expectException(InvalidArgumentException::class);
        (new Chat(new Generic('https://example.test/chat', 'key', $this->client([], $empty)), new Model('other')))->chat($request);
    }

    public function testNativeMissingUsageIsUnknown(): void
    {
        $requests = [];
        $body = json_decode((string) $this->native('initial')->getBody(), true);
        unset($body['usageMetadata']);
        $response = (new Chat(new GoogleNative('key', $this->client([new Response(json_encode($body))], $requests)), new Model('model')))->chat('Draw');
        $this->assertNull($response->getUsage()->getTotalTokens());
    }

    public function testNativeToolRoundTripRetainsObjectArgumentsAndSignature(): void
    {
        $requests = [];
        $call = new Response('{"candidates":[{"content":{"role":"model","parts":[{"functionCall":{"id":"native-id","name":"work","args":{"options":{}}},"thoughtSignature":"signed-tool"}]},"finishReason":"STOP"}]}');
        $chat = new Chat(new GoogleNative('key', $this->client([$call, $this->native('done')], $requests)), new Model('model'));
        $executions = 0;
        $tool = new Tool('work', 'Work', ['type' => 'object', 'properties' => ['options' => ['type' => 'object']]],
            function () use (&$executions): string { $executions++; return 'completed'; });
        $chat->chat(new ChatRequest(new Conversation(['Work']), tools: new ToolCollection($tool)));
        $this->assertSame(1, $executions);
        $this->assertSame('signed-tool', $requests[1]['body']['contents'][1]['parts'][0]['thoughtSignature']);
        $this->assertSame('native-id', $requests[1]['body']['contents'][2]['parts'][0]['functionResponse']['id']);
        $this->assertSame('completed', $requests[1]['body']['contents'][2]['parts'][0]['functionResponse']['response']['result']);
        $this->assertSame('work', $requests[0]['body']['tools'][0]['functionDeclarations'][0]['name']);
    }

    public function testNativeIncompatibleOptionsFailBeforeHttp(): void
    {
        $requests = [];
        $chat = new Chat(new GoogleNative('key', $this->client([], $requests)), new Model('model'));
        $this->expectException(InvalidArgumentException::class);
        $chat->chat(new ChatRequest(new Conversation(['Draw']), serviceTier: ServiceTier::FLEX));
    }

    public function testNativeExtensionsCannotBypassSamplingConstraints(): void
    {
        $requests = [];
        $chat = new Chat(new GoogleNative('key', $this->client([], $requests), generationConfig: ['topK' => 10]), new Model('model', tunable: false));
        $this->expectException(InvalidArgumentException::class);
        $chat->chat('Draw');
    }

    public function testStandaloneImageGeneration(): void
    {
        $requests = [];
        $generator = new ImageGenerator(new GoogleNative('key', $this->client([$this->native('generated')], $requests)), new Model('gemini-3.1-flash-image'));
        $response = $generator->generate(new ImageRequest('Draw a farm', options: ['imageSize' => '1K'], aspectRatio: '16:9'));
        $this->assertCount(1, $response->getImages());
        $this->assertSame('generated', $response->getImages()[0]->getBytes());
        $this->assertSame('image/png', $response->getImages()[0]->mimeType);
        $this->assertSame(30, $response->getUsage()->getTotalTokens());
        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-image:generateContent', $requests[0]['uri']);
        $this->assertSame([['role' => 'user', 'parts' => [['text' => 'Draw a farm']]]], $requests[0]['body']['contents']);
        $this->assertSame(['IMAGE'], $requests[0]['body']['generationConfig']['responseModalities']);
        $this->assertSame(['imageSize' => '1K', 'aspectRatio' => '16:9'], $requests[0]['body']['generationConfig']['imageConfig']);
    }

    /** @return iterable<string, array{ImageRequest}> */
    public static function unsupportedImageRequests(): iterable
    {
        yield 'several images' => [new ImageRequest('Draw', 2)];
        yield 'foreign option' => [new ImageRequest('Draw', options: ['quality' => 'high'])];
    }

    #[DataProvider('unsupportedImageRequests')]
    public function testUnsupportedImageRequestsFailBeforeHttp(ImageRequest $request): void
    {
        $requests = [];
        $generator = new ImageGenerator(new GoogleNative('key', $this->client([], $requests)), new Model('gemini-3.1-flash-image'));
        $this->expectException(InvalidArgumentException::class);
        $generator->generate($request);
    }

    public function testImageResponseWithoutImageIsInvalid(): void
    {
        $requests = [];
        $text = new Response('{"candidates":[{"content":{"role":"model","parts":[{"text":"No image"}]},"finishReason":"STOP"}]}');
        $generator = new ImageGenerator(new GoogleNative('key', $this->client([$text], $requests)), new Model('gemini-3.1-flash-image'));
        try {
            $generator->generate('Draw');
            $this->fail('A response without image must fail.');
        } catch (ProviderException $failure) {
            $this->assertSame(FailureKind::INVALID_RESPONSE, $failure->getKind());
        }
    }

    private function native(string $image): ResponseInterface
    {
        return new Response(json_encode(['candidates' => [['content' => ['role' => 'model', 'parts' => [
            ['text' => 'Here it is'], ['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($image)], 'thoughtSignature' => 'signed-' . $image],
        ]], 'finishReason' => 'STOP']], 'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 20, 'totalTokenCount' => 30]], JSON_THROW_ON_ERROR));
    }

    /** @param list<ResponseInterface> $responses */
    private function client(array $responses, array &$requests): ClientInterface
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(count($responses)))->method('sendRequest')->willReturnCallback(
            static function (RequestInterface $request) use (&$responses, &$requests): ResponseInterface {
                $requests[] = ['uri' => (string) $request->getUri(), 'key' => $request->getHeaderLine('x-goog-api-key'),
                    'authorization' => $request->getHeaderLine('Authorization'), 'body' => json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR)];
                return array_shift($responses);
            },
        );
        return $client;
    }
}
