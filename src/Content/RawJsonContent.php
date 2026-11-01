<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Content;

use ByCerfrance\LlmApiLib\Model\Capability;
use InvalidArgumentException;
use Override;
use Stringable;

/** Already-encoded JSON, validated without decoding or re-encoding its values. */
readonly class RawJsonContent implements ContentInterface, Stringable
{
    /** @throws InvalidArgumentException When the document is not valid JSON. */
    public function __construct(private string $content)
    {
        if (!json_validate($content)) {
            throw new InvalidArgumentException('Raw JSON content must be a valid JSON document.');
        }
    }

    public function getContent(): string
    {
        return $this->content;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->content;
    }

    #[Override]
    public function jsonSerialize(bool $encapsulated = false): array|string
    {
        return $encapsulated ? ['type' => 'text', 'text' => $this->content] : $this->content;
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        return [Capability::TEXT];
    }
}
