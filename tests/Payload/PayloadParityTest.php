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
use ByCerfrance\LlmApiLib\Completion\Message\Choices;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonObjectFormat;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonSchemaFormat;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\TextFormat;
use ByCerfrance\LlmApiLib\Completion\Tool\Tool;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Payload\Builder\CompletionBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Tests\Payload\Support\PayloadReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayloadBuilder::class)]
class PayloadParityTest extends TestCase
{
    public function testCompletionParityAgainstIndependentReference(): void
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
        );

        $modernPayload = (new PayloadBuilder())->build($completion);
        $legacyPayload = (new PayloadBuilder([
            new CompletionBuilder(maxCompletionTokens: false),
        ]))->build($completion);

        $this->assertEquals(PayloadReference::completion($completion, true), $modernPayload);
        $this->assertEquals(PayloadReference::completion($completion, false), $legacyPayload);
        $this->assertLegacyJsonSerializeMatches($completion, PayloadReference::completion($completion, false));
    }

    public function testMessageParityAgainstIndependentReference(): void
    {
        $messages = [
            new Message('hello'),
            new AssistantMessage(toolCalls: [new ToolCall('call-1', 'sum', ['a' => 1])]),
            new ToolResult('call-1', ['ok' => true]),
        ];

        $choices = new Choices(new Message('first'), new Message('second'));
        $choices->setPreferred(1);
        $messages[] = $choices;

        $payloadBuilder = new PayloadBuilder();

        foreach ($messages as $message) {
            $this->assertEquals(
                PayloadReference::message($message),
                $payloadBuilder->build($message),
            );
            $this->assertLegacyJsonSerializeMatches($message, PayloadReference::message($message));
        }
    }

    public function testContentParityAgainstIndependentReference(): void
    {
        $contents = [
            new TextContent('hello'),
            new JsonContent(['a' => 1]),
            new InputAudioContent('abc', 'wav'),
            new ImageUrlContent('https://example.com/image.png', 'high'),
            new DocumentUrlContent('https://example.com/file.pdf', 'file.pdf', 'auto'),
            new ArrayContent(new TextContent('hello'), new JsonContent(['b' => 2])),
        ];

        $payloadBuilder = new PayloadBuilder();

        foreach ($contents as $content) {
            $this->assertEquals(
                PayloadReference::content($content),
                $payloadBuilder->build($content),
            );
            $this->assertLegacyJsonSerializeMatches($content, PayloadReference::content($content));
        }
    }

    public function testToolParityAgainstIndependentReference(): void
    {
        $toolA = new Tool('sum', 'sum numbers', ['type' => 'object'], fn(array $args) => $args);
        $toolB = new Tool('mul', 'multiply numbers', ['type' => 'object'], fn(array $args) => $args);
        $toolCollection = new ToolCollection($toolA, $toolB);
        $toolCall = new ToolCall('call-1', 'sum', ['a' => 1, 'b' => 2]);

        $payloadBuilder = new PayloadBuilder();

        $this->assertEquals(PayloadReference::tool($toolA), $payloadBuilder->build($toolA));
        $this->assertEquals(PayloadReference::toolCall($toolCall), $payloadBuilder->build($toolCall));
        $this->assertEquals(PayloadReference::toolCollection($toolCollection), $payloadBuilder->build($toolCollection));

        $this->assertLegacyJsonSerializeMatches($toolA, PayloadReference::tool($toolA));
        $this->assertLegacyJsonSerializeMatches($toolCall, PayloadReference::toolCall($toolCall));
        $this->assertLegacyJsonSerializeMatches($toolCollection, PayloadReference::toolCollection($toolCollection));

        // ToolCall with additionalFields
        $toolCallWithExtra = new ToolCall(
            'call-2',
            'check_flight',
            ['flight' => 'AA100'],
            additionalFields: [
                'extra_content' => ['google' => ['thought_signature' => 'sig-abc']],
            ],
        );
        $this->assertEquals(
            PayloadReference::toolCall($toolCallWithExtra),
            $payloadBuilder->build($toolCallWithExtra),
        );
        $this->assertLegacyJsonSerializeMatches($toolCallWithExtra, PayloadReference::toolCall($toolCallWithExtra));
    }

    public function testResponseFormatParityAgainstIndependentReference(): void
    {
        $formats = [
            new TextFormat(),
            new JsonObjectFormat(),
            new JsonSchemaFormat('Schema', ['type' => 'object'], true),
        ];

        $payloadBuilder = new PayloadBuilder();

        foreach ($formats as $format) {
            $expected = PayloadReference::responseFormat($format);
            $this->assertEquals($expected, $payloadBuilder->build($format));
            $this->assertLegacyJsonSerializeMatches($format, $expected);
        }
    }

    private function assertLegacyJsonSerializeMatches(object $value, mixed $expected): void
    {
        if (!is_callable([$value, 'jsonSerialize'])) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertEquals($this->normalize($expected), $this->normalize($value->jsonSerialize()));
    }

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
