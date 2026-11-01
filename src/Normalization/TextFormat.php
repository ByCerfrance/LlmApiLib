<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

enum TextFormat: string
{
    case UNKNOWN = 'unknown';
    case PLAIN_TEXT = 'plain_text';
    case JSON = 'json';
}
