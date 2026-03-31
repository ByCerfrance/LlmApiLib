<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Tool;

use ByCerfrance\LlmApiLib\Completion\Content\ContentInterface;
use ByCerfrance\LlmApiLib\Completion\Content\JsonContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use Override;

/**
 * Represents the result of a tool execution, formatted as a message.
 */
readonly class ToolResult implements MessageInterface
{
    private ContentInterface $content;

    public function __construct(
        private string $toolCallId,
        string|array|ContentInterface $content,
    ) {
        $this->content = match (true) {
            is_string($content) => new TextContent($content),
            is_array($content) => new JsonContent($content),
            default => $content,
        };
    }

    public function getToolCallId(): string
    {
        return $this->toolCallId;
    }

    #[Override]
    public function getRole(): RoleEnum
    {
        return RoleEnum::TOOL;
    }

    #[Override]
    public function getContent(): ContentInterface
    {
        return $this->content;
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'role' => $this->getRole(),
            'tool_call_id' => $this->toolCallId,
            'content' => (string)$this->content,
        ];
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        return $this->content->requiredCapabilities();
    }
}
