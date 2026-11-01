<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Chat\ChatResponseInterface;
use Override;

readonly class NullResponseNormalizer implements ResponseNormalizerInterface
{
    #[Override]
    public function normalize(ChatResponseInterface $response): ChatResponseInterface
    {
        return $response;
    }
}
