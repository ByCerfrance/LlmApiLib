<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Tool;

use CallbackFilterIterator;
use Override;
use RuntimeException;
use Traversable;

/**
 * Decorator that filters tools from any ToolCollectionInterface.
 *
 * Supports include and exclude patterns:
 * - ['tool_a', 'tool_b'] → only tool_a and tool_b are allowed
 * - ['!tool_c'] → all tools except tool_c
 * - ['tool_a', '!tool_c'] → include takes priority: only tool_a
 *
 * Usage:
 *
 *     $filtered = new FilteredToolCollection($mcpServer, ['get_weather', '!admin_tool']);
 *     $completion->withTools($filtered);
 */
readonly class FilteredToolCollection implements ToolCollectionInterface
{
    /** @var string[] */
    private array $includes;

    /** @var string[] */
    private array $excludes;

    /**
     * @param ToolCollectionInterface $inner The tool collection to filter
     * @param string[] $operations Filter rules: tool names to include, prefixed with '!' to exclude
     */
    public function __construct(
        private ToolCollectionInterface $inner,
        array $operations,
    ) {
        $this->includes = array_values(
            array_filter($operations, fn(string $op): bool => !str_starts_with($op, '!'))
        );
        $this->excludes = array_map(
            fn(string $op): string => ltrim($op, '!'),
            array_values(array_filter($operations, fn(string $op): bool => str_starts_with($op, '!')))
        );
    }

    #[Override]
    public function get(string $name): ToolInterface
    {
        if (!$this->isAllowed($name)) {
            throw new RuntimeException(
                sprintf('Tool "%s" not found in collection', $name)
            );
        }

        return $this->inner->get($name);
    }

    #[Override]
    public function has(string $name): bool
    {
        return $this->isAllowed($name) && $this->inner->has($name);
    }

    #[Override]
    public function execute(ToolCall $toolCall): ToolResult
    {
        if (!$this->isAllowed($toolCall->name)) {
            throw new RuntimeException(
                sprintf('Tool "%s" is not allowed by filter', $toolCall->name)
            );
        }

        return $this->inner->execute($toolCall);
    }

    #[Override]
    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new CallbackFilterIterator(
            $this->inner->getIterator(),
            fn(ToolInterface $tool): bool => $this->isAllowed($tool->getName()),
        );
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return iterator_to_array($this->getIterator(), false);
    }

    /**
     * Check if a tool name is allowed by the filter rules.
     *
     * If includes are specified, only those are allowed (excludes are ignored).
     * Otherwise, everything except excludes is allowed.
     */
    private function isAllowed(string $name): bool
    {
        if (!empty($this->includes)) {
            return in_array($name, $this->includes, true);
        }

        return !in_array($name, $this->excludes, true);
    }
}
