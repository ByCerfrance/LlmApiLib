<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\ResponseFormat;

use ByCerfrance\LlmApiLib\Capability;
use Override;

class TextFormat implements ResponseFormatInterface
{
    #[Override]
    public function jsonSerialize(): mixed
    {
        return ['type' => 'text'];
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        return [
            Capability::TEXT,
        ];
    }
}
