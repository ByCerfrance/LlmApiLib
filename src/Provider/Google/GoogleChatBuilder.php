<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider\Google;

use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use InvalidArgumentException;
use Override;

/** @internal Validates Google compatibility options without silently stripping parameters. */
readonly class GoogleChatBuilder implements BuilderInterface
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
        $thinking = $payload['extra_body']['google']['thinking_config'] ?? null;
        if (isset($payload['reasoning_effort']) && is_array($thinking)
            && (isset($thinking['thinking_level']) || isset($thinking['thinking_budget']))) {
            throw new InvalidArgumentException('Configure reasoning_effort or Google thinking level/budget, not both.');
        }

        return $payload;
    }
}
