<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

interface TokenUsageInterface
{
    /** @return int|null Input tokens including cached input, or null when unknown. */
    public function getPromptTokens(): ?int;

    /** @return int|null Output tokens including billable reasoning exactly once, or null when unknown. */
    public function getCompletionTokens(): ?int;

    /** @return int|null Provider-reported total tokens, not necessarily input plus output, or null when unknown. */
    public function getTotalTokens(): ?int;

    /** @return int|null Cache-read tokens already included in input, or null when unknown; zero means no cache hit. */
    public function getCachedTokens(): ?int;
}
