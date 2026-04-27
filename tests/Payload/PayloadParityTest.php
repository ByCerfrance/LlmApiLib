<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Payload;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Completion\Content\DocumentUrlContent;
use ByCerfrance\LlmApiLib\Completion\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Completion\Content\InputAudioContent;
use ByCerfrance\LlmApiLib\Completion\Content\JsonContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\Choice;
use ByCerfrance\LlmApiLib\Completion\Message\Choices;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonObjectFormat;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonSchemaFormat;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\TextFormat;
use ByCerfrance\LlmApiLib\Completion\ReasoningEffort;
use ByCerfrance\LlmApiLib\Completion\ServiceTier;
use ByCerfrance\LlmApiLib\Completion\Tool\AbstractTool;
use ByCerfrance\LlmApiLib\Completion\Tool\Tool;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\Builder\MistralCompletionBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that PayloadBuilder::build() produces the same result as json_decode(json_encode())
 * for all domain objects (the native PHP JsonSerializable cascade).
 */
#[CoversClass(PayloadBuilder::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(MistralCompletionBuilder::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ReasoningEffort::class)]
#[UsesClass(ServiceTier::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(JsonContent::class)]
#[UsesClass(ArrayContent::class)]
#[UsesClass(InputAudioContent::class)]
#[UsesClass(ImageUrlContent::class)]
#[UsesClass(DocumentUrlContent::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Choice::class)]
#[UsesClass(Choices::class)]
#[UsesClass(JsonObjectFormat::class)]
#[UsesClass(JsonSchemaFormat::class)]
#[UsesClass(TextFormat::class)]
#[UsesClass(Tool::class)]
#[UsesClass(AbstractTool::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolCollection::class)]
#[UsesClass(ToolResult::class)]
class PayloadParityTest extends TestCase
{
    public function testCompletionParity(): void
    {
        $tool = new Tool('sum', 'sum numbers', ['type' => 'object'], fn(array $args) => $args);
        $completion = new Completion(
            messages: [new Message('hello')],
            responseFormat: new JsonObjectFormat(),
            model: 'gpt-test',
            maxTokens: 123,
            temperature: 0.2,
            top_p: 0.7,
            seed: 42,
            tools: new ToolCollection($tool),
            serviceTier: ServiceTier::AUTO,
            reasoningEffort: ReasoningEffort::HIGH,
            parallelToolCalls: false,
        );

        $payload = (new PayloadBuilder())->build($completion);
        $native = $this->normalize($completion);

        $this->assertEquals($native, $this->normalize($payload));
    }

    public function testMistralCompletionParity(): void
    {
        $completion = new Completion(
            messages: [new Message('hello')],
            model: 'mistral-large',
            maxTokens: 500,
        );

        $payload = (new PayloadBuilder([new MistralCompletionBuilder()]))->build($completion);

        $this->assertArrayHasKey('max_tokens', $payload);
        $this->assertArrayNotHasKey('max_completion_tokens', $payload);
        $this->assertSame(500, $payload['max_tokens']);
    }

    public function testMessageParity(): void
    {
        $payloadBuilder = new PayloadBuilder();

        $messages = [
            new Message('hello'),
            new AssistantMessage(toolCalls: [new ToolCall('call-1', 'sum', ['a' => 1])]),
            new ToolResult('call-1', ['ok' => true]),
        ];

        foreach ($messages as $message) {
            $payload = $payloadBuilder->build($message);
            $native = $this->normalize($message);
            $this->assertEquals($native, $this->normalize($payload));
        }
    }

    public function testChoicesUsePreferredMessage(): void
    {
        $choices = new Choices(new Choice(new Message('first')), new Choice(new Message('second')));
        $choices->setPreferred(1);

        $payload = (new PayloadBuilder())->build($choices);

        $this->assertSame('second', $payload['content']);
        $this->assertSame('user', $payload['role']);
    }

    public function testContentParity(): void
    {
        $payloadBuilder = new PayloadBuilder();

        $contents = [
            new TextContent('hello'),
            new JsonContent(['a' => 1]),
            new InputAudioContent('abc', 'wav'),
            new ImageUrlContent('https://example.com/image.png', 'high'),
            new DocumentUrlContent('https://example.com/file.pdf', 'file.pdf', 'auto'),
            new ArrayContent(new TextContent('hello'), new JsonContent(['b' => 2])),
        ];

        foreach ($contents as $content) {
            $payload = $payloadBuilder->build($content);
            $native = $this->normalize($content);
            $this->assertEquals($native, $this->normalize($payload));
        }
    }

    public function testToolParity(): void
    {
        $payloadBuilder = new PayloadBuilder();

        $toolA = new Tool('sum', 'sum numbers', ['type' => 'object'], fn(array $args) => $args);
        $toolCall = new ToolCall('call-1', 'sum', ['a' => 1, 'b' => 2]);
        $toolCallWithExtra = new ToolCall(
            'call-2',
            'check_flight',
            ['flight' => 'AA100'],
            additionalFields: [
                'extra_content' => ['google' => ['thought_signature' => 'sig-abc']],
            ],
        );
        $toolCollection = new ToolCollection($toolA);

        $this->assertEquals($this->normalize($toolA), $this->normalize($payloadBuilder->build($toolA)));
        $this->assertEquals($this->normalize($toolCall), $this->normalize($payloadBuilder->build($toolCall)));
        $this->assertEquals(
            $this->normalize($toolCallWithExtra),
            $this->normalize($payloadBuilder->build($toolCallWithExtra))
        );
        $this->assertEquals(
            $this->normalize($toolCollection),
            $this->normalize($payloadBuilder->build($toolCollection))
        );
    }

    public function testResponseFormatParity(): void
    {
        $payloadBuilder = new PayloadBuilder();

        $formats = [
            new TextFormat(),
            new JsonObjectFormat(),
            new JsonSchemaFormat('Schema', ['type' => 'object'], true),
        ];

        foreach ($formats as $format) {
            $payload = $payloadBuilder->build($format);
            $native = $this->normalize($format);
            $this->assertEquals($native, $this->normalize($payload));
        }
    }

    /**
     * Normalize a value through JSON encode/decode to compare structures.
     */
    private function normalize(mixed $value): mixed
    {
        return json_decode(
            json_encode($value, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
