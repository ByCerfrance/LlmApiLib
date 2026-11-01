<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Normalization;

use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\ProviderAssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\SystemMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Chat\Streaming\ChatEvent;
use ByCerfrance\LlmApiLib\Chat\Streaming\ChatStream;
use ByCerfrance\LlmApiLib\Chat\Streaming\StreamControl;
use ByCerfrance\LlmApiLib\Chat\Streaming\StreamingChat;
use ByCerfrance\LlmApiLib\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Content\JsonContent;
use ByCerfrance\LlmApiLib\Content\RawJsonContent;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Content\TextFormat;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Normalization\ImageNormalizerInterface;
use ByCerfrance\LlmApiLib\Normalization\MessageNormalizer;
use ByCerfrance\LlmApiLib\Normalization\NormalizedChatResponse;
use ByCerfrance\LlmApiLib\Normalization\NormalizedChatResult;
use ByCerfrance\LlmApiLib\Normalization\NullPromptNormalizer;
use ByCerfrance\LlmApiLib\Normalization\NullResponseNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptTarget;
use ByCerfrance\LlmApiLib\Normalization\ResponseNormalizer;
use ByCerfrance\LlmApiLib\Normalization\ResponseNormalizerInterface;
use ByCerfrance\LlmApiLib\Normalization\TextNormalizer;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Provider\ProviderStreamEvent;
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
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PromptNormalizer::class)]
#[CoversClass(ResponseNormalizer::class)]
#[CoversClass(MessageNormalizer::class)]
#[CoversClass(NormalizedChatResult::class)]
#[UsesClass(Chat::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(ChatEvent::class)]
#[UsesClass(ChatStream::class)]
#[UsesClass(StreamControl::class)]
#[UsesClass(StreamingChat::class)]
#[UsesClass(ProviderStreamEvent::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(Message::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(ProviderAssistantMessage::class)]
#[UsesClass(SystemMessage::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(ArrayContent::class)]
#[UsesClass(ImageUrlContent::class)]
#[UsesClass(JsonContent::class)]
#[UsesClass(RawJsonContent::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(Model::class)]
#[UsesClass(NormalizedChatResponse::class)]
#[UsesClass(NullPromptNormalizer::class)]
#[UsesClass(NullResponseNormalizer::class)]
#[UsesClass(TextNormalizer::class)]
#[UsesClass(TextFormat::class)]
#[UsesClass(PromptTarget::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(AbstractTool::class)]
#[UsesClass(InvocationRegistry::class)]
#[UsesClass(ToolSchema::class)]
#[UsesClass(JsonSchemaArgumentsValidator::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolCollection::class)]
#[UsesClass(ToolExecutor::class)]
#[UsesClass(ToolResult::class)]
#[UsesClass(TokenUsage::class)]
class ChatNormalizationTest extends TestCase
{
    public function testEveryProviderTurnIsNormalizedWithoutChangingHistoryOrTools(): void
    {
        $call = new ToolCall('call-1', 'lookup', ['query' => 'a  b']);
        $provider = new ScriptedChatProvider([
            ScriptedChatProvider::toolTurn($call),
            ScriptedChatProvider::answer(' { "answer": 42 } '),
            ScriptedChatProvider::answer('Follow-up'),
        ]);
        $tools = new ToolCollection(new Tool('lookup', 'Lookup', [
            'type' => 'object', 'properties' => ['query' => ['type' => 'string']],
        ], static function (array $arguments): string {
            self::assertSame(['query' => 'a  b'], $arguments);
            return ' { "value": 1.2300 } ';
        }));
        $chat = new Chat($provider, new Model('demo'), promptNormalizer: new PromptNormalizer(
            targets: [PromptTarget::USER, PromptTarget::TOOL_RESULT],
            textNormalizer: new TextNormalizer(minifyJson: true),
        ), responseNormalizer: new ResponseNormalizer(textNormalizer: new TextNormalizer(minifyJson: true)));
        $request = new ChatRequest(new Conversation([new SystemMessage(' { "system": true } '), ' { "question": 1 } ']), tools: $tools);
        $response = $chat->chat($request);

        $sent = iterator_to_array($provider->requests[1]->getRequest()->getConversation(), false);
        self::assertSame(' { "system": true } ', (string) $sent[0]->getContent());
        self::assertSame('{"question":1}', (string) $sent[1]->getContent());
        self::assertSame($call, $sent[2]->getToolCalls()[0]);
        self::assertSame('call-1', $sent[3]->getToolCallId());
        self::assertSame('{"value":1.2300}', (string) $sent[3]->getContent());
        self::assertSame($tools, $provider->requests[1]->getRequest()->getTools());
        self::assertSame('{"answer":42}', $response->getText());
        self::assertInstanceOf(NormalizedChatResponse::class, $response);
        self::assertSame(' { "answer": 42 } ', $response->getOriginalResponse()->getText());
        $history = iterator_to_array($response->toConversation(), false);
        self::assertSame(' { "question": 1 } ', (string) $history[1]->getContent());
        self::assertSame(' { "value": 1.2300 } ', (string) $history[3]->getContent());
        self::assertSame(' { "answer": 42 } ', (string) $history[4]->getContent());
        self::assertCount(2, $request->getConversation());
        self::assertCount(2, $response->getTurns());

        $chat->chat($response->reply('Continue'));
        $continued = iterator_to_array($provider->requests[2]->getRequest()->getConversation(), false);
        self::assertSame(' { "answer": 42 } ', (string) $continued[4]->getContent());
    }

    public function testTargetsPreserveNativeAndUnknownMessagesAndToolArguments(): void
    {
        $call = new ToolCall('id', 'lookup', ['text' => 'a  b']);
        $native = new ProviderAssistantMessage(new TextContent('  native  '), [$call], 'native', 'provider', 'model', ['signature' => 'keep']);
        $custom = new readonly class('  custom  ') extends UserMessage {};
        $messages = [new SystemMessage('  system  '), new UserMessage('  user  '), new AssistantMessage('  assistant  ', [$call]), new ToolResult('id', '  result  '), $native, $custom, new Message('  generic  ')];
        $request = new ChatRequest(new Conversation($messages), temperature: 0);
        $normalizer = new PromptNormalizer(PromptTarget::cases(), new TextNormalizer(trimText: true), textFormat: TextFormat::PLAIN_TEXT);
        $normalized = $normalizer->normalize($request);
        $actual = iterator_to_array($normalized->getConversation(), false);
        self::assertSame(['system', 'user', 'assistant', 'result', '  native  ', '  custom  ', 'generic'], array_map(static fn(MessageInterface $message): string => (string) $message->getContent(), $actual));
        self::assertSame($native, $actual[4]);
        self::assertSame($custom, $actual[5]);
        self::assertSame($call, $actual[2]->getToolCalls()[0]);
        self::assertSame(0, $normalized->getTemperature());
        self::assertSame($normalized, $normalizer->normalize($normalized));
        self::assertSame($request, (new PromptNormalizer())->normalize($request));
    }

    public function testMultipartContentUsesExplicitFormatResolverAndImageNormalizer(): void
    {
        $image = new ImageUrlContent('data:image/png;base64,original', 'low');
        $replacement = new ImageUrlContent('data:image/png;base64,normalized', 'low');
        $images = $this->createMock(ImageNormalizerInterface::class);
        $images->expects(self::once())->method('normalize')->with($image)->willReturn($replacement);
        $json = new JsonContent(['already' => 'compact']);
        $content = new ArrayContent('  plain text  ', "    code\n", $image, $json);
        $normalizer = new PromptNormalizer(
            targets: [PromptTarget::USER],
            textNormalizer: new TextNormalizer(trimText: true),
            imageNormalizer: $images,
            textFormatResolver: static fn(MessageInterface $message, TextContent $part): TextFormat => $part->getContent() === '  plain text  ' ? TextFormat::PLAIN_TEXT : TextFormat::UNKNOWN,
        );
        $result = $normalizer->normalize(new ChatRequest(new Conversation([new UserMessage($content)])));
        $message = iterator_to_array($result->getConversation(), false)[0];
        $parts = iterator_to_array($message->getContent(), false);
        self::assertSame('plain text', (string) $parts[0]);
        self::assertSame("    code\n", (string) $parts[1]);
        self::assertSame($replacement, $parts[2]);
        self::assertSame($json, $parts[3]);
        self::assertSame('  plain text  ', (string) iterator_to_array($content, false)[0]);
    }

    public function testNativeOutputPreservesMetadataAndOriginalContinuation(): void
    {
        $native = new ProviderAssistantMessage(new TextContent(' { "ok": true } '), [], 'protocol', 'provider', 'model', ['signature' => 'keep']);
        $usage = new TokenUsage(10, 2, 12);
        $result = new ChatResult($native, $usage, FinishReason::STOP, 'provider', 'model', 'request');
        $original = new ChatResponse(new Conversation([$native]), $result);
        $normalizer = new ResponseNormalizer(textNormalizer: new TextNormalizer(minifyJson: true));
        $normalized = $normalizer->normalize($original);
        self::assertSame('{"ok":true}', $normalized->getText());
        self::assertSame($original, $normalized->getOriginalResponse());
        self::assertSame($original->toConversation(), $normalized->toConversation());
        self::assertSame(['signature' => 'keep'], $normalized->getResult()->getMessage()->getNativeContent());
        self::assertSame($usage, $normalized->getResult()->getUsage());
        self::assertSame(FinishReason::STOP, $normalized->getResult()->getFinishReason());
        self::assertSame('provider', $normalized->getResult()->getProviderId());
        self::assertSame('model', $normalized->getResult()->getModelName());
        self::assertSame('request', $normalized->getResult()->getRequestId());
        self::assertSame([], $normalized->getResult()->getToolCalls());
        self::assertSame($normalized, $normalizer->normalize($normalized));
        self::assertSame($original, (new ResponseNormalizer())->normalize($original));
    }

    public function testInvalidTargetIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PromptNormalizer(['user']);
    }

    public function testContentFormatsTakePrecedenceOverDefaultsAndSurviveNormalization(): void
    {
        $markdown = new TextContent("Keep  this break  \n```json\n{ \"label\": \"A  B\" }\n```", format: TextFormat::MARKDOWN);
        $plain = new TextContent('  Plain   text  ', format: TextFormat::PLAIN_TEXT);
        $unknown = new TextContent('  Keep   unknown  ', format: TextFormat::UNKNOWN);
        $inherited = new TextContent('  Inherit   default  ');
        $request = new ChatRequest(new Conversation([new UserMessage(new ArrayContent($markdown, $plain, $unknown, $inherited))]));
        $normalizer = new PromptNormalizer([PromptTarget::USER], new TextNormalizer(minifyJson: true, trimText: true, collapseSpaces: true), textFormat: TextFormat::PLAIN_TEXT);
        $normalized = $normalizer->normalize($request);
        $message = iterator_to_array($normalized->getConversation(), false)[0];
        $parts = iterator_to_array($message->getContent(), false);
        self::assertSame("Keep  this break  \n```json\n{\"label\":\"A  B\"}\n```", $parts[0]->getContent());
        self::assertSame(TextFormat::MARKDOWN, $parts[0]->getFormat());
        self::assertSame('Plain text', $parts[1]->getContent());
        self::assertSame(TextFormat::PLAIN_TEXT, $parts[1]->getFormat());
        self::assertSame($unknown, $parts[2]);
        self::assertSame('Inherit default', $parts[3]->getContent());
        self::assertNull($parts[3]->getFormat());
        self::assertSame($normalized, $normalizer->normalize($normalized));
        self::assertSame('  Plain   text  ', $plain->getContent());
    }

    public function testResolverOverridesTextContentFormat(): void
    {
        $content = new TextContent('  Preserve   text  ', format: TextFormat::PLAIN_TEXT);
        $request = new ChatRequest(new Conversation([new UserMessage($content)]));
        $normalizer = new PromptNormalizer([PromptTarget::USER], new TextNormalizer(trimText: true, collapseSpaces: true),
            textFormatResolver: static fn(MessageInterface $message, TextContent $part): TextFormat => TextFormat::UNKNOWN,
        );
        self::assertSame($request, $normalizer->normalize($request));
    }

    public function testRawJsonIsNormalizedAsJsonAndRemainsTypedInInputAndOutput(): void
    {
        $json = ' { "amount": 1.2300, "label": "A  B" } ';
        $raw = new RawJsonContent($json);
        $text = new TextNormalizer(minifyJson: true, trimText: true, collapseSpaces: true);
        $resolver = static function (): never { throw new RuntimeException('Raw JSON must not use a text format resolver.'); };
        $promptNormalizer = new PromptNormalizer([PromptTarget::USER], $text, textFormat: TextFormat::PLAIN_TEXT, textFormatResolver: $resolver);
        $request = new ChatRequest(new Conversation([new UserMessage($raw)]));
        $normalized = $promptNormalizer->normalize($request);
        $content = iterator_to_array($normalized->getConversation(), false)[0]->getContent();
        self::assertInstanceOf(RawJsonContent::class, $content);
        self::assertSame('{"amount":1.2300,"label":"A  B"}', $content->getContent());
        self::assertSame($json, $raw->getContent());
        self::assertSame($normalized, $promptNormalizer->normalize($normalized));

        $result = new ChatResult(new AssistantMessage($raw), new TokenUsage(), FinishReason::STOP, 'test');
        $original = new ChatResponse(new Conversation([$result->getMessage()]), $result);
        $response = (new ResponseNormalizer($text, textFormatResolver: $resolver))->normalize($original);
        self::assertSame('{"amount":1.2300,"label":"A  B"}', $response->getText());
        self::assertInstanceOf(RawJsonContent::class, $response->getResult()->getMessage()->getContent());
        self::assertSame($json, $response->getOriginalResponse()->getText());
        self::assertSame($original, $response->getOriginalResponse());
        $multipartResult = new ChatResult(new AssistantMessage(new ArrayContent('Answer: ', $raw)), new TokenUsage(), FinishReason::STOP, 'test');
        self::assertSame('Answer: ' . $json, (new ChatResponse(new Conversation([$multipartResult->getMessage()]), $multipartResult))->getText());
    }

    public function testStreamingNormalizesEachTurnAndFinalResponseButNotDeltas(): void
    {
        $call = new ToolCall('lookup-1', 'lookup', []);
        $provider = new ScriptedStreamingProvider([
            ScriptedStreamingProvider::turn(ScriptedChatProvider::toolTurn($call)),
            ScriptedStreamingProvider::turn(ScriptedChatProvider::answer(' { "ok": true } '), ' { "ok":', ' true } '),
        ]);
        $tool = new Tool('lookup', 'Lookup', ['type' => 'object'], static fn(): string => ' { "value": 42 } ');
        $chat = new StreamingChat($provider, new Model('model'),
            promptNormalizer: new PromptNormalizer([PromptTarget::USER, PromptTarget::TOOL_RESULT], new TextNormalizer(minifyJson: true)),
            responseNormalizer: new ResponseNormalizer(new TextNormalizer(minifyJson: true)),
        );
        $request = new ChatRequest(new Conversation([' { "question": 1 } ']), tools: new ToolCollection($tool));
        $events = iterator_to_array($chat->stream($request), false);
        self::assertSame(['tool.started', 'tool.completed', 'text.delta', 'text.delta', 'completed'], array_column($events, 'type'));
        self::assertSame(' { "value": 42 } ', (string) $events[1]->toolResult->getContent());
        self::assertSame(' { "ok": true } ', $events[2]->text . $events[3]->text);
        $response = $events[4]->response;
        self::assertSame('{"ok":true}', $response->getText());
        self::assertSame(' { "ok": true } ', $response->getOriginalResponse()->getText());
        $sent = iterator_to_array($provider->requests[1]->getRequest()->getConversation(), false);
        self::assertSame('{"question":1}', (string) $sent[0]->getContent());
        self::assertSame('{"value":42}', (string) $sent[2]->getContent());
        $history = iterator_to_array($response->toConversation(), false);
        self::assertSame(' { "question": 1 } ', (string) $history[0]->getContent());
        self::assertSame(' { "value": 42 } ', (string) $history[2]->getContent());
        self::assertSame(2, $provider->released);
    }

    public function testCancelledStreamDoesNotNormalizeAnIncompleteResponse(): void
    {
        $provider = new ScriptedStreamingProvider([
            ScriptedStreamingProvider::turn(ScriptedChatProvider::answer('complete'), 'partial', 'rest'),
        ]);
        $normalizer = $this->createMock(ResponseNormalizerInterface::class);
        $normalizer->expects(self::never())->method('normalize');
        $stream = (new StreamingChat($provider, new Model('model'), responseNormalizer: $normalizer))->stream('Question');
        foreach ($stream as $event) {
            self::assertSame('partial', $event->text);
            $stream->close();
        }
        self::assertSame(1, $provider->released);
    }

    public function testOutputNormalizationFailureReleasesStreamAndEmitsNoCompletion(): void
    {
        $provider = new ScriptedStreamingProvider([
            ScriptedStreamingProvider::turn(ScriptedChatProvider::answer('complete'), 'complete'),
        ]);
        $normalizer = $this->createStub(ResponseNormalizerInterface::class);
        $failure = new RuntimeException('Normalization failed.');
        $normalizer->method('normalize')->willThrowException($failure);
        $stream = (new StreamingChat($provider, new Model('model'), responseNormalizer: $normalizer))->stream('Question');
        $types = [];
        try {
            foreach ($stream as $event) {
                $types[] = $event->type;
            }
            self::fail('Expected the normalizer failure.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
        self::assertSame(['text.delta'], $types);
        self::assertSame(1, $provider->released);
    }
}
