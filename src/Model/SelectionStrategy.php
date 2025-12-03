<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Model;

enum SelectionStrategy: string
{
    case CHEAP = 'cheap';
    case BALANCED = 'balanced';
    case BEST_QUALITY = 'best_quality';
}
