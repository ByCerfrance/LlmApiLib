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
}
