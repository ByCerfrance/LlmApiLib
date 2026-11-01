<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Chat\ChatResponseInterface;
use RuntimeException;

interface ResponseNormalizerInterface
{
    /**
     * Normalize the final answer presented to the application.
     *
     * Preserve original continuation history, usage and provider metadata.
     * Implementations transforming the answer must retain access to the original response.
     *
     * @param ChatResponseInterface $response Complete response, assembled when streaming.
     * @return ChatResponseInterface Response presented to the application.
     * @throws RuntimeException When normalization cannot be performed.
     */
    public function normalize(ChatResponseInterface $response): ChatResponseInterface;
}
