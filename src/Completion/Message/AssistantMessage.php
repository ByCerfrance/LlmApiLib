<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Message;

use ByCerfrance\LlmApiLib\Completion\Content\ContentInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use Override;

/**
 * Assistant message with optional tool calls.
 */
readonly class AssistantMessage extends Message
{
    /** @var ToolCall[] */
    private array $toolCalls;

    /**
     * @param string|ContentInterface|null $content
     * @param ToolCall[] $toolCalls
     */
    public function __construct(
        string|ContentInterface|null $content = null,
        array $toolCalls = [],
    ) {
        parent::__construct($content, RoleEnum::ASSISTANT);

        $this->toolCalls = array_filter(
            $toolCalls,
            fn($v) => $v instanceof ToolCall,
        );
    }

    /**
     * Get tool calls.
     *
     * @return ToolCall[]
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Check if message has tool calls.
     *
     * @return bool
     */
    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    #[Override]
    public function jsonSerialize(): array
    {
        if ($this->hasToolCalls()) {
            return [
                'role' => $this->getRole(),
                'tool_calls' => $this->getToolCalls(),
            ];
        }

        return parent::jsonSerialize();
    }
}
