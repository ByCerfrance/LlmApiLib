<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;
use RuntimeException;

interface PromptNormalizerInterface
{
    /**
     * Normalize a copy for one provider turn without modifying the original history.
     *
     * Protocol identifiers, tool call arguments and native signatures must be preserved.
     *
     * @param ChatRequestInterface $request Request containing the current turn's history.
     * @return ChatRequestInterface Request to send to the provider.
     * @throws RuntimeException When normalization cannot be performed.
     */
    public function normalize(ChatRequestInterface $request): ChatRequestInterface;
}
