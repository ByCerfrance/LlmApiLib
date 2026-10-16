<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider\Mistral;

use ByCerfrance\LlmApiLib\Chat\ReasoningEffort;
use ByCerfrance\LlmApiLib\Chat\ToolChoice;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use InvalidArgumentException;
use Override;

/** @internal Lossless Mistral mappings; incompatible explicit options are rejected. */
readonly class MistralChatBuilder implements BuilderInterface
{
    #[Override]
    public function supports(mixed $value, BuildContext $context): bool
    {
        return $value instanceof ChatPayload;
    }

    #[Override]
    public function build(mixed $value, BuildContext $context): array
    {
        if (!$value instanceof ChatPayload) {
            throw new InvalidArgumentException('Expected a root chat payload.');
        }
        $payload = $value->jsonSerialize();
        if (isset($payload['max_completion_tokens'])) {
            if (isset($payload['max_tokens'])) {
                throw new InvalidArgumentException('Specify only one maximum token option.');
            }
            $payload['max_tokens'] = $payload['max_completion_tokens'];
            unset($payload['max_completion_tokens']);
        }
        if (isset($payload['max_tokens']) && (!is_int($payload['max_tokens']) || $payload['max_tokens'] < 1)) {
            throw new InvalidArgumentException('Maximum tokens must be a positive integer.');
        }
        if (isset($payload['service_tier'])) {
            throw new InvalidArgumentException('Mistral does not support service_tier.');
        }
        $effort = $payload['reasoning_effort'] ?? null;
        if ($effort instanceof ReasoningEffort) {
            $effort = $effort->value;
        }
        if ($effort !== null && !in_array($effort, ['high', 'none'], true)) {
            throw new InvalidArgumentException('Mistral reasoning_effort must be high or none.');
        }
        if ($effort !== null) {
            $payload['reasoning_effort'] = $effort;
        }
        if (($payload['tool_choice'] ?? null) === ToolChoice::REQUIRED || ($payload['tool_choice'] ?? null) === 'required') {
            $payload['tool_choice'] = 'any';
        }

        return $payload;
    }
}
