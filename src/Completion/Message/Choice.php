<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Message;

use ByCerfrance\LlmApiLib\Completion\FinishReason;

/**
 * A single choice from an LLM completion response.
 *
 * Associates a message with its finish reason and index in the response.
 */
readonly class Choice
{
    public function __construct(
        public MessageInterface $message,
        public ?FinishReason $finishReason = null,
        public int $index = 0,
    ) {
    }
}
