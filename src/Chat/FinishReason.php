<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

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
    /** Output blocked or cut by the provider's safety or policy filters. */
    case CONTENT_FILTER = 'content_filter';
    /** Output blocked because it reproduces existing content, for example copyrighted material. */
    case RECITATION = 'recitation';

    /**
     * Parse a finish_reason string, handling composite formats like "content_filter: RECITATION".
     *
     * Some providers (e.g. Google Gemini) return detailed finish reasons with a colon-separated
     * suffix. This method normalizes the value by extracting the base reason before attempting
     * enum resolution; a RECITATION suffix is reported as such.
     */
    public static function parse(string $value): ?self
    {
        [$base, $detail] = array_map(trim(...), explode(':', $value, 2) + [1 => '']);
        $reason = self::tryFrom($base);

        return $reason === self::CONTENT_FILTER && strtoupper($detail) === 'RECITATION' ? self::RECITATION : $reason;
    }
}
