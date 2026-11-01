<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;
use ByCerfrance\LlmApiLib\Image\ImageRequestInterface;
use RuntimeException;

interface PromptNormalizerInterface
{
    /**
     * Normalize a copy for one provider turn without modifying the original history.
     *
     * Protocol identifiers, tool call arguments and native signatures must be preserved.
     *
     * @param ChatRequestInterface|ImageRequestInterface $request Original chat or image-generation request.
     * @return ($request is ChatRequestInterface ? ChatRequestInterface : ImageRequestInterface) Request of the same kind to send to the provider.
     * @throws RuntimeException When normalization cannot be performed.
     */
    public function normalize(ChatRequestInterface|ImageRequestInterface $request): ChatRequestInterface|ImageRequestInterface;
}
