<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

use JsonSerializable;

interface UsageInterface extends JsonSerializable
{
    /**
     * Get prompt tokens.
     *
     * @return int
     */
    public function getPromptTokens(): int;

    /**
     * Get completion tokens.
     *
     * @return int
     */
    public function getCompletionTokens(): int;

    /**
     * Get total tokens.
     *
     * @return int
     */
    public function getTotalTokens(): int;

    /**
     * Get cached prompt tokens.
     *
     * @return int
     */
    public function getCachedTokens(): int;
}
