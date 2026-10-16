<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;

readonly class ChatEvent
{
    public function __construct(
        public string $type,
        public ?string $text = null,
        public ?int $toolIndex = null,
        public ?ToolCall $toolCall = null,
        public ?ToolResult $toolResult = null,
        public ?ChatResponseInterface $response = null,
    ) {
    }
}
