<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Model;

enum CostTier: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
