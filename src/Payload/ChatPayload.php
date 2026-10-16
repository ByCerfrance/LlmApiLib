<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload;

use JsonSerializable;
use Override;

/** @internal Identifies the root chat payload so builders never rewrite nested schemas. */
readonly class ChatPayload implements JsonSerializable
{
    /** @param array<string, mixed> $payload */
    public function __construct(private array $payload)
    {
    }

    /** @return array<string, mixed> */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->payload;
    }
}
