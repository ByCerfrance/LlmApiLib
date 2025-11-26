<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\ResponseFormat;

use ByCerfrance\LlmApiLib\Capability;
use Override;

readonly class JsonObjectFormat implements ResponseFormatInterface
{
    #[Override]
    public function jsonSerialize(): array
    {
        return ['type' => 'json_object'];
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        return [
            Capability::JSON_OUTPUT,
        ];
    }
}
