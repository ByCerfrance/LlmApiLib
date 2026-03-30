<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Mcp;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollectionInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use Override;
use RuntimeException;
use Traversable;

/**
 * Abstract base for remote tool providers (MCP, OpenAPI, etc.).
 *
 * Implements ToolCollectionInterface with lazy initialization:
 * tools are discovered on first access unless initialize() is called manually.
 */
abstract class AbstractServer implements ToolCollectionInterface
{
    private bool $initialized = false;

    /** @var array<string, ToolInterface> */
    protected array $tools = [];

    /**
     * Ensure the server is initialized (lazy initialization).
     */
    protected function ensureInitialized(): void
    {
        if (!$this->initialized) {
            $this->doInitialize();
            $this->initialized = true;
        }
    }

    /**
     * Mark the server as initialized.
     */
    protected function markInitialized(): void
    {
        $this->initialized = true;
    }

    /**
     * Check if the server has been initialized.
     */
    protected function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Reset the initialization state (for re-initialization after shutdown).
     */
    protected function resetInitialized(): void
    {
        $this->initialized = false;
    }

    /**
     * Perform the actual initialization (discovery of tools).
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
