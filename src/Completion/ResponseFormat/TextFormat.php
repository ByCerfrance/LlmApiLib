<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\ResponseFormat;

use Override;

class TextFormat implements ResponseFormatInterface
{
    #[Override]
    public function jsonSerialize(): mixed
    {
        return ['type' => 'text'];
    }
}
