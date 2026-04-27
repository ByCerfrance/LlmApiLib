<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion;

/**
 * Tool choice strategy for the API request.
 *
 * Controls whether and how the model should use tools.
 *
 * @see https://platform.openai.com/docs/api-reference/chat/create#chat-create-tool_choice
 */
enum ToolChoice: string
{
    case AUTO = 'auto';
    case NONE = 'none';
    case REQUIRED = 'required';
}
