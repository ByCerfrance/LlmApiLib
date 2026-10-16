<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

interface TokenUsageInterface
{
    /** @return int|null Reported input tokens, or null when unknown. */
    public function getPromptTokens(): ?int;

    /** @return int|null Reported output tokens, or null when unknown. */
    public function getCompletionTokens(): ?int;

    /** @return int|null Reported total tokens, or null when unknown. */
    public function getTotalTokens(): ?int;

    /** @return int|null Reported cached input tokens, or null when unknown. */
    public function getCachedTokens(): ?int;
}
