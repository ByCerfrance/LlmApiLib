<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Tool;

use JsonSerializable;

interface ToolInterface extends JsonSerializable
{
    /**
     * Get tool name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get tool description.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get parameters JSON Schema.
     *
     * @return array
     */
    public function getParameters(): array;

    /**
     * Execute the tool with given arguments.
     *
     * @param array $arguments
     *
     * @return mixed
     */
    public function execute(array $arguments): mixed;
}
