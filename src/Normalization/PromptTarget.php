<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

enum PromptTarget: string
{
    case SYSTEM = 'system';
    case USER = 'user';
    case ASSISTANT_HISTORY = 'assistant_history';
    case TOOL_RESULT = 'tool_result';
    case IMAGE_PROMPT = 'image_prompt';
    case IMAGE_REFERENCE = 'image_reference';
}
