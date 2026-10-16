<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tool;

enum ToolErrorPolicy: string
{
    case STOP = 'stop';
    case RETURN_ERROR = 'return_error';
}
