<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Content;

use ByCerfrance\LlmApiLib\Capability;
use Override;
use RuntimeException;
use Stringable;

readonly class TextContent implements ContentInterface, Stringable
{
    public static function fromFile(mixed $file, array $placeholders = []): self
    {
        $content = match ($debugType = get_debug_type($file)) {
            'string' => file_get_contents($file),
            'resource (stream)' => stream_get_contents($file, offset: 0),
            default => throw new RuntimeException(sprintf('Unable to get content from a %s', $debugType)),
        };

        return new self($content, $placeholders);
    }

    public function __construct(
        private string|int|float|Stringable $content,
        private array $placeholders = [],
    ) {
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
        $placeholdersKeys = array_map(
            fn($key) => sprintf('{%s}', $key),
            array_keys($this->placeholders),
        );
        $placeholdersValues = array_values($this->placeholders);

        return str_replace($placeholdersKeys, $placeholdersValues, (string)$this->content);
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
