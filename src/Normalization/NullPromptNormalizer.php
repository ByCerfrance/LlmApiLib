<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;
use Override;

readonly class NullPromptNormalizer implements PromptNormalizerInterface
{
    #[Override]
    public function normalize(ChatRequestInterface $request): ChatRequestInterface
    {
        return $request;
    }
}
