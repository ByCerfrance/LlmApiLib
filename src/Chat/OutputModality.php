<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

enum OutputModality: string
{
    case TEXT = 'TEXT';
    case IMAGE = 'IMAGE';
}
