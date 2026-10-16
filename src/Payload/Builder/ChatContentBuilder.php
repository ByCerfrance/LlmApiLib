<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload\Builder;

use ByCerfrance\LlmApiLib\Completion\Tool\ToolSchema;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Provider\OpenAi;
use InvalidArgumentException;
use Override;

/** @internal Adapt message content only, never fields inside schemas or tool arguments. */
readonly class ChatContentBuilder implements BuilderInterface
{
    #[Override]
    public function supports(mixed $value, BuildContext $context): bool
    {
        return $value instanceof ChatPayload;
    }

    #[Override]
    public function build(mixed $value, BuildContext $context): ChatPayload
    {
        if (!$value instanceof ChatPayload) {
            throw new InvalidArgumentException('Expected a root chat payload.');
        }
        $payload = (new PayloadBuilder())->build($value->jsonSerialize());
        foreach ($payload['tools'] ?? [] as $index => $tool) {
            $schema = $tool['function']['parameters'] ?? null;
            if (is_array($schema) || is_object($schema)) {
                $payload['tools'][$index]['function']['parameters'] = ToolSchema::normalize($schema);
            }
        }
        foreach ($payload['messages'] ?? [] as $index => $message) {
            $content = $message['content'] ?? null;
            if (!is_array($content)) {
                continue;
            }
            $parts = isset($content['type']) ? [$content] : $content;
            if ($context->provider instanceof OpenAi) {
                foreach ($parts as $partIndex => $part) {
                    if (($part['type'] ?? null) !== 'document_url') {
                        continue;
                    }
                    $url = $part['document_url']['url'] ?? '';
                    if (!is_string($url) || !str_starts_with($url, 'data:application/pdf;base64,')) {
                        throw new InvalidArgumentException('OpenAI chat document input requires inline PDF data; use DocumentUrlContent::fromFile().');
                    }
                    $parts[$partIndex] = ['type' => 'file', 'file' => [
                        'filename' => $part['document_name'] ?? 'document.pdf',
                        'file_data' => $url,
                    ]];
                }
            }
            $payload['messages'][$index]['content'] = $parts;
        }

        return new ChatPayload($payload);
    }
}
