<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tool;

use ArrayIterator;
use Override;
use RuntimeException;
use Traversable;

/**
 * Base for remote tool collections (MCP, OpenAPI, etc.).
 *
 * Tools are discovered on first access; subclasses fill $tools in doInitialize().
 */
abstract class AbstractLazyToolCollection implements ToolCollectionInterface
{
    private bool $initialized = false;

    /** @var array<string, ToolInterface> */
    protected array $tools = [];

    /**
     * Discover the tools once, on first access.
     */
    protected function ensureInitialized(): void
    {
        if (!$this->initialized) {
            $this->doInitialize();
            $this->initialized = true;
        }
    }

    /**
     * Allow a new discovery on next access (e.g. after a session shutdown).
     */
    protected function resetInitialized(): void
    {
        $this->initialized = false;
    }

    /**
     * Perform the actual discovery of tools.
     */
    abstract protected function doInitialize(): void;

    #[Override]
    public function get(string $name): ToolInterface
    {
        $this->ensureInitialized();

        return $this->tools[$name] ?? throw new RuntimeException(
            sprintf('Tool "%s" not found in collection', $name)
        );
    }

    #[Override]
    public function has(string $name): bool
    {
        $this->ensureInitialized();

        return isset($this->tools[$name]);
    }

    #[Override]
    abstract public function execute(ToolCall $toolCall): ToolResult;

    #[Override]
    public function count(): int
    {
        $this->ensureInitialized();

        return count($this->tools);
    }

    #[Override]
    public function getIterator(): Traversable
    {
        $this->ensureInitialized();

        return new ArrayIterator(array_values($this->tools));
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $this->ensureInitialized();

        return array_values($this->tools);
    }
}
