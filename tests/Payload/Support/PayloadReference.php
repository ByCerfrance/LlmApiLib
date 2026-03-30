<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Payload\Support;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Completion\Content\ContentInterface;
use ByCerfrance\LlmApiLib\Completion\Content\DocumentUrlContent;
use ByCerfrance\LlmApiLib\Completion\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Completion\Content\InputAudioContent;
use ByCerfrance\LlmApiLib\Completion\Content\JsonContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonObjectFormat;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonSchemaFormat;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\ResponseFormatInterface;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\TextFormat;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use JsonSerializable;
use ReflectionProperty;
use RuntimeException;

final class PayloadReference
{
    public static function completion(CompletionInterface $completion, bool $maxCompletionTokens = true): array
    {
        $maxTokensKey = $maxCompletionTokens ? 'max_completion_tokens' : 'max_tokens';

        return array_filter(
            [
                $maxTokensKey => $completion->getMaxTokens(),
                'messages' => array_map(
                    fn(MessageInterface $message) => self::message($message),
                    iterator_to_array($completion->getIterator(), false),
                ),
                'model' => null !== $completion->getModel() ? (string)$completion->getModel() : null,
                'response_format' => null !== $completion->getResponseFormat() ? self::responseFormat($completion->getResponseFormat()) : null,
                'stream' => false,
                'temperature' => $completion->getTemperature(),
                'top_p' => $completion->getTopP(),
                'seed' => $completion->getSeed(),
                'tools' => null !== $completion->getTools() ? self::toolCollection($completion->getTools()) : null,
            ],
            fn($v) => null !== $v,
        );
    }

    public static function message(MessageInterface $message): array
    {
        if ($message instanceof ToolResult) {
            return [
                'role' => $message->getRole()->value,
                'tool_call_id' => $message->getToolCallId(),
                'content' => (string)$message->getContent(),
            ];
        }

        if ($message instanceof AssistantMessage && $message->hasToolCalls()) {
            return [
                'role' => $message->getRole()->value,
                'content' => null,
                'tool_calls' => array_map(
                    fn(ToolCall $toolCall) => self::toolCall($toolCall),
                    $message->getToolCalls(),
                ),
            ];
        }

        return [
            'role' => $message->getRole()->value,
            'content' => self::content($message->getContent()),
        ];
    }

    public static function content(ContentInterface $content, bool $encapsulated = false): mixed
    {
        if ($content instanceof ArrayContent) {
            return array_map(
                fn(ContentInterface $item) => self::content($item, true),
                iterator_to_array($content->getIterator(), false),
            );
        }

        if ($content instanceof TextContent || $content instanceof JsonContent) {
            if (!$encapsulated) {
                return $content->getContent();
            }

            return [
                'type' => 'text',
                'text' => $content->getContent(),
            ];
        }

        if ($content instanceof InputAudioContent) {
            return [
                'type' => 'input_audio',
                'input_audio' => [
                    'data' => $content->getData(),
                    'format' => $content->getFormat(),
                ],
            ];
        }

        if ($content instanceof ImageUrlContent) {
            return [
                'type' => 'image_url',
                'image_url' => array_filter([
                    'url' => (string)$content->getUrl(),
                    'detail' => $content->getDetail(),
                ]),
            ];
        }

        if ($content instanceof DocumentUrlContent) {
            return array_filter([
                'type' => 'document_url',
                'document_url' => array_filter([
                    'url' => (string)$content->getUrl(),
                    'detail' => $content->getDetail(),
                ]),
                'document_name' => $content->getName(),
            ]);
        }

        throw new RuntimeException(sprintf('Unsupported content type "%s"', $content::class));
    }

    public static function toolCollection(ToolCollection $toolCollection): array
    {
        return array_map(
            fn(ToolInterface $tool) => self::tool($tool),
            iterator_to_array($toolCollection->getIterator(), false),
        );
    }

    public static function tool(ToolInterface $tool): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => (object)$tool->getParameters(),
            ],
        ];
    }

    public static function toolCall(ToolCall $toolCall): array
    {
        $payload = [
            'id' => $toolCall->id,
            'type' => 'function',
            'function' => [
                'name' => $toolCall->name,
                'arguments' => json_encode($toolCall->arguments),
            ],
        ];

        if (null !== $toolCall->additionalFields) {
            $payload = array_merge($payload, $toolCall->additionalFields);
        }

        return $payload;
    }

    public static function responseFormat(ResponseFormatInterface $responseFormat): mixed
    {
        if ($responseFormat instanceof TextFormat) {
            return ['type' => 'text'];
        }

        if ($responseFormat instanceof JsonObjectFormat) {
            return ['type' => 'json_object'];
        }

        if ($responseFormat instanceof JsonSchemaFormat) {
            return [
                'type' => 'json_schema',
                'json_schema' => array_filter([
                    'name' => self::readPrivateProperty($responseFormat, 'name'),
                    'schema' => (object)self::readPrivateProperty($responseFormat, 'schema'),
                    'strict' => self::readPrivateProperty($responseFormat, 'strict'),
                ], fn($v) => null !== $v),
            ];
        }

        throw new RuntimeException(sprintf('Unsupported response format "%s"', $responseFormat::class));
    }

    private static function readPrivateProperty(object $object, string $name): mixed
    {
        $property = new ReflectionProperty($object, $name);

        return $property->getValue($object);
    }
}
