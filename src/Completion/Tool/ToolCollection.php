<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Tool;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Override;
use RuntimeException;
use Traversable;

/**
 * @implements IteratorAggregate<int, ToolInterface>
 */
readonly class ToolCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var array<string, ToolInterface> */
    private array $tools;

    public function __construct(ToolInterface ...$tools)
    {
        $indexed = [];
        foreach ($tools as $tool) {
            $indexed[$tool->getName()] = $tool;
        }
        $this->tools = $indexed;
    }

    /**
     * Get tool by name.
     *
     * @param string $name
     *
     * @return ToolInterface
     * @throws RuntimeException If tool not found
     */
    public function get(string $name): ToolInterface
    {
        return $this->tools[$name] ?? throw new RuntimeException(
            sprintf('Tool "%s" not found in collection', $name)
        );
    }

    /**
     * Check if tool exists.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Execute a tool call.
     *
     * @param ToolCall $toolCall
     *
     * @return ToolResult
     */
    public function execute(ToolCall $toolCall): ToolResult
    {
        $tool = $this->get($toolCall->name);
        $result = $tool->execute($toolCall->arguments);

        if ($result instanceof ToolResult) {
            return $result;
        }

        return new ToolResult(
            toolCallId: $toolCall->id,
            content: is_string($result) ? $result : (array) $result,
        );
    }

    #[Override]
    public function count(): int
    {
        return count($this->tools);
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator(array_values($this->tools));
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return array_values($this->tools);
    }
}
