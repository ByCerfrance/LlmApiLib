<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Content;

use ByCerfrance\LlmApiLib\Model\Capability;
use JsonException;
use JsonSerializable;
use Override;
use stdClass;
use Stringable;

readonly class JsonContent implements ContentInterface, Stringable
{
    public function __construct(private string|int|float|bool|null|array|stdClass|JsonSerializable $content)
    {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->getContent();
    }

    /**
     * Get content.
     *
     * @return string
     * @throws JsonException
     */
    public function getContent(): string
    {
        return json_encode($this->content, JSON_THROW_ON_ERROR);
    }

    #[Override]
    public function jsonSerialize(bool $encapsulated = false): array|string
    {
        if (true === $encapsulated) {
            return [
                'type' => 'text',
                'text' => $this->getContent(),
            ];
        }

        return $this->getContent();
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        return [
            Capability::TEXT,
        ];
    }
}
