<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat\Streaming;

use ByCerfrance\LlmApiLib\Chat\ChatResponseInterface;
use ByCerfrance\LlmApiLib\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Tool\ToolResult;

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
