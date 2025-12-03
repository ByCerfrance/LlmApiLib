<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Model;

enum QualityTier: string
{
    case BASIC = 'basic';
    case GOOD = 'good';
    case PREMIUM = 'premium';
}
