<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tool;

use JsonException;
use JsonSerializable;
use Override;
use RuntimeException;
use stdClass;

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
        private ?string $argumentsJson = null,
    ) {
        if ($argumentsJson !== null && json_decode($argumentsJson, true, flags: JSON_THROW_ON_ERROR) !== $arguments) {
            throw new RuntimeException('Tool argument data does not match its JSON representation.');
        }
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
        if (!is_string($data['id'] ?? null) || $data['id'] === ''
            || ($data['type'] ?? null) !== 'function'
            || !is_array($data['function'] ?? null)
            || !is_string($data['function']['name'] ?? null) || $data['function']['name'] === ''
            || !is_string($data['function']['arguments'] ?? null)) {
            throw new RuntimeException('Invalid function invocation in provider response.');
        }
        $json = $data['function']['arguments'];
        try {
            $arguments = json_decode($json, flags: JSON_THROW_ON_ERROR);
            if (!$arguments instanceof stdClass) {
                throw new RuntimeException('Tool arguments must be a JSON object.');
            }
        } catch (JsonException $exception) {
            throw new RuntimeException('Tool arguments contain invalid JSON.', previous: $exception);
        }
        $additional = array_diff_key($data, array_flip(self::KNOWN_FIELDS));

        return new self(
            id: $data['id'],
            name: $data['function']['name'],
            arguments: json_decode($json, true, flags: JSON_THROW_ON_ERROR),
            additionalFields: $additional ?: null,
            argumentsJson: $json,
        );
    }

    /** Original JSON preserves nested object/array distinctions for schema validation. */
    public function getArgumentsJson(): string
    {
        return $this->argumentsJson ?? json_encode((object) $this->arguments, JSON_THROW_ON_ERROR);
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $payload = [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => $this->getArgumentsJson(),
            ],
        ];

        if (null !== $this->additionalFields) {
            $payload += $this->additionalFields;
        }

        return $payload;
    }
}
