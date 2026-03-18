<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Tool;

use Closure;
use Override;

readonly class Tool extends AbstractTool
{
    private Closure $callback;

    /**
     * @param string $name Tool name (must match pattern ^[a-zA-Z0-9_-]+$)
     * @param string $description Tool description for the LLM
     * @param array $parameters JSON Schema for parameters
     * @param callable $callback Callback to execute when tool is called
     */
    public function __construct(
        string $name,
        string $description,
        array $parameters,
        callable $callback,
    ) {
        parent::__construct($name, $description, $parameters);
        $this->callback = $callback(...);
    }

    #[Override]
    public function execute(array $arguments): mixed
    {
        return ($this->callback)($arguments);
    }
}
