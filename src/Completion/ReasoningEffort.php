<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion;

/**
 * Reasoning effort level for reasoning models.
 *
 * Controls how much reasoning the model performs before generating a response.
 *
 * @see https://platform.openai.com/docs/api-reference/chat/create#chat-create-reasoning_effort
 */
enum ReasoningEffort: string
{
    case NONE = 'none';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case XHIGH = 'xhigh';

    /**
     * Get the fallback effort level.
     *
     * @return self|null
     */
    public function fallback(): ?self
    {
        return match ($this) {
            self::XHIGH => self::HIGH,
            self::HIGH => self::MEDIUM,
            self::MEDIUM => self::LOW,
            self::LOW => self::NONE,
            self::NONE => null,
        };
    }
}
