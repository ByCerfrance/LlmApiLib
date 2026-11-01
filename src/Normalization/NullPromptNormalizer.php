<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;
use ByCerfrance\LlmApiLib\Image\ImageRequestInterface;
use Override;

readonly class NullPromptNormalizer implements PromptNormalizerInterface
{
    #[Override]
    public function normalize(ChatRequestInterface|ImageRequestInterface $request): ChatRequestInterface|ImageRequestInterface
    {
        return $request;
    }
}
