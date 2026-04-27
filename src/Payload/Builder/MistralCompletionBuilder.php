<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload\Builder;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\ReasoningEffort;
use ByCerfrance\LlmApiLib\Completion\ToolChoice;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use Override;

/**
 * Mistral-specific builder that adapts completion payloads for the Mistral API.
 *
 * - Renames `max_completion_tokens` to `max_tokens`
 * - Strips `service_tier` (unsupported)
 * - Falls back `reasoning_effort` to supported values (HIGH, NONE)
 * - Remaps `tool_choice` value `required` to `any` (Mistral naming)
 *
 * @internal
 */
readonly class MistralCompletionBuilder implements BuilderInterface
{
    #[Override]
    public function supports(mixed $value, BuildContext $context): bool
    {
        return $value instanceof CompletionInterface;
    }

    #[Override]
    public function build(mixed $value, BuildContext $context): array
    {
        /** @var CompletionInterface $value */
        $payload = $value->jsonSerialize();

        if (array_key_exists('max_completion_tokens', $payload)) {
            $payload['max_tokens'] = $payload['max_completion_tokens'];
            unset($payload['max_completion_tokens']);
        }

        unset($payload['service_tier']);

        if (isset($payload['tool_choice']) && $payload['tool_choice'] === ToolChoice::REQUIRED) {
            $payload['tool_choice'] = 'any';
        }

        if (isset($payload['reasoning_effort']) && $payload['reasoning_effort'] instanceof ReasoningEffort) {
            $supported = [ReasoningEffort::HIGH, ReasoningEffort::NONE];
            $effort = $payload['reasoning_effort'];

            while (null !== $effort && !in_array($effort, $supported, true)) {
                $effort = $effort->fallback();
            }

            if (null !== $effort) {
                $payload['reasoning_effort'] = $effort;
            } else {
                unset($payload['reasoning_effort']);
            }
        }

        return $payload;
    }
}
