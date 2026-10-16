<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Tool;

interface ArgumentsValidatorInterface
{
    /**
     * @param ToolInterface $tool Tool definition and JSON Schema.
     * @param ToolCall $call Invocation to validate before execution.
     * @return void
     * @throws \InvalidArgumentException When arguments do not satisfy the schema.
     * @throws \RuntimeException When the schema cannot be evaluated.
     */
    public function validate(ToolInterface $tool, ToolCall $call): void;
}
