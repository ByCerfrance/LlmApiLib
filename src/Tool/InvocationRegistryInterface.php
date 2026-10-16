<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tool;

interface InvocationRegistryInterface
{
    /**
     * @param ToolCall $call Invocation identity and arguments.
     * @return ToolResult|null Previously completed result, or null.
     * @throws \RuntimeException If an ID is reused with a different tool or arguments.
     */
    public function get(ToolCall $call): ?ToolResult;

    /**
     * @param ToolCall $call Completed invocation.
     * @param ToolResult $result Result to retain; the first completed result wins.
     * @return void
     * @throws \RuntimeException For inconsistent invocation/result identifiers.
     */
    public function record(ToolCall $call, ToolResult $result): void;
}
