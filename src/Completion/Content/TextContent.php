<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Content;

use Override;
use Stringable;

readonly class TextContent implements ContentInterface, Stringable
{
    public function __construct(private string|int|float|Stringable $content)
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
     */
    public function getContent(): string
    {
        return (string)$this->content;
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
}
