<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Tool;

use Override;

readonly abstract class AbstractTool implements ToolInterface
{
    /**
     * @param string $name Tool name (must match pattern ^[a-zA-Z0-9_-]+$)
     * @param string $description Tool description for the LLM
     * @param array $parameters JSON Schema for parameters
     */
    public function __construct(
        private string $name,
        private string $description,
        private array $parameters,
    ) {
    }

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[Override]
    public function getDescription(): string
    {
        return $this->description;
    }

    #[Override]
    public function getParameters(): array
    {
        return $this->parameters;
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => (object)$this->parameters,
            ],
        ];
    }
}
