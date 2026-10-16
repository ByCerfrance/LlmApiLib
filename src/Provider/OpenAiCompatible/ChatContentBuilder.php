<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider\OpenAiCompatible;

use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Tool\ToolSchema;
use InvalidArgumentException;
use Override;

/**
 * Normalizes tool schemas and message content into lists of parts.
 *
 * Adapts message content only, never fields inside schemas or tool arguments.
 *
 * @internal
 */
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
            if (is_array($content)) {
                $payload['messages'][$index]['content'] = isset($content['type']) ? [$content] : $content;
            }
        }

        // Keep the marker so provider-specific builders still see the root.
        return new ChatPayload($payload);
    }
}
