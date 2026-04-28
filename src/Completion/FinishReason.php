<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion;

/**
 * Reason why the LLM stopped generating tokens.
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-choices
 */
enum FinishReason: string
{
    case STOP = 'stop';
    case LENGTH = 'length';
    case TOOL_CALLS = 'tool_calls';
    case CONTENT_FILTER = 'content_filter';

    /**
     * Parse a finish_reason string, handling composite formats like "content_filter: RECITATION".
     *
     * Some providers (e.g. Google Gemini) return detailed finish reasons with a colon-separated
     * suffix. This method normalizes the value by extracting the base reason before attempting
     * enum resolution.
     */
    public static function parse(string $value): ?self
    {
        return self::tryFrom(trim(explode(':', $value, 2)[0]));
    }
}
