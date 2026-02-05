<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Message;

use ByCerfrance\LlmApiLib\Completion\Content\ContentInterface;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use Override;

/**
 * Assistant message with optional tool calls.
 */
readonly class AssistantMessage implements MessageInterface
{
    private ContentInterface $content;
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
        if (is_string($content)) {
            $this->content = new TextContent($content);
        } elseif (null === $content) {
            $this->content = new TextContent('');
        } else {
            $this->content = $content;
        }

        $this->toolCalls = array_filter(
            $toolCalls,
            fn($v) => $v instanceof ToolCall,
        );
    }

    #[Override]
    public function getRole(): RoleEnum
    {
        return RoleEnum::ASSISTANT;
    }

    #[Override]
    public function getContent(): ContentInterface
    {
        return $this->content;
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
        $data = [
            'role' => $this->getRole(),
        ];

        if (!$this->hasToolCalls()) {
            $data['content'] = $this->content;
        } else {
            $data['content'] = null;
            $data['tool_calls'] = $this->toolCalls;
        }

        return $data;
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        return $this->content->requiredCapabilities();
    }
}
