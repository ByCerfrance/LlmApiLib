<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\ResponseFormat;

use ByCerfrance\LlmApiLib\Capability;
use JsonSerializable;
use Override;

readonly class JsonSchemaFormat implements ResponseFormatInterface
{
    public function __construct(
        private string $name,
        private array|JsonSerializable $schema,
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
                    'schema' => (object)$this->schema,
                    'strict' => $this->strict,
                ],
                fn($v) => null !== $v,
            ),
        ];
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        return [
            Capability::JSON_SCHEMA,
        ];
    }
}
