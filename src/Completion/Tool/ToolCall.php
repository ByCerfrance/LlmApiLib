<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Tool;

use JsonSerializable;
use Override;

/**
 * Represents a tool call requested by the LLM.
 */
readonly class ToolCall implements JsonSerializable
{
    private const array KNOWN_FIELDS = ['id', 'type', 'function'];

    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
        public ?array $additionalFields = null,
    ) {
    }

    /**
     * Create from API response array.
     *
     * @param array $data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $additional = array_diff_key($data, array_flip(self::KNOWN_FIELDS));

        return new self(
            id: $data['id'],
            name: $data['function']['name'],
            arguments: json_decode($data['function']['arguments'], true) ?? [],
            additionalFields: $additional ?: null,
        );
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $payload = [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => json_encode($this->arguments),
            ],
        ];

        if (null !== $this->additionalFields) {
            $payload = array_merge($payload, $this->additionalFields);
        }

        return $payload;
    }
}
