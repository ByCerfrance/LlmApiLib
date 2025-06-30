<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\ResponseFormat;

use JsonSerializable;
use Override;

readonly class JsonSchemaFormat implements ResponseFormatInterface
{
    public function __construct(
        private string $name,
        private array|JsonSerializable $schema,
        private ?string $description = null,
        private bool $strict = false,
    ) {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => array_filter(
                [
                    'name' => $this->name,
                    'description' => $this->description,
                    'strict' => $this->strict,
                    'schema' => (object)$this->schema,
                ],
                fn($v) => null !== $v,
            ),
        ];
    }
}
