<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\ResponseFormat;

use Override;

class JsonObjectFormat implements ResponseFormatInterface
{
    #[Override]
    public function jsonSerialize(): array
    {
        return ['type' => 'json_object'];
    }
}
