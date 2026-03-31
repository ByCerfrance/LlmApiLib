<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload\Builder;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use Override;

/**
 * Mistral-specific builder that renames `max_completion_tokens` to `max_tokens`.
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

        return $payload;
    }
}
