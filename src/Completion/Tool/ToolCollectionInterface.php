<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Tool;

use Countable;
use IteratorAggregate;
use JsonSerializable;
use RuntimeException;

/**
 * @extends IteratorAggregate<int, ToolInterface>
 */
interface ToolCollectionInterface extends Countable, IteratorAggregate, JsonSerializable
{
    /**
     * Get tool by name.
     *
     * @param string $name
     *
     * @return ToolInterface
     * @throws RuntimeException If tool not found
     */
    public function get(string $name): ToolInterface;

    /**
     * Check if tool exists.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * Execute a tool call.
     *
     * @param ToolCall $toolCall
     *
     * @return ToolResult
     */
    public function execute(ToolCall $toolCall): ToolResult;
}
