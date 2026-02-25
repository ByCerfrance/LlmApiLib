<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Tool;

use Closure;
use Override;

readonly class Tool implements ToolInterface
{
    private Closure $callback;

    /**
     * @param string $name Tool name (must match pattern ^[a-zA-Z0-9_-]+$)
     * @param string $description Tool description for the LLM
     * @param array $parameters JSON Schema for parameters
     * @param callable $callback Callback to execute when tool is called
     */
    public function __construct(
        private string $name,
        private string $description,
        private array $parameters,
        callable $callback,
    ) {
        $this->callback = $callback(...);
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
    public function execute(array $arguments): mixed
    {
        return ($this->callback)($arguments);
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
