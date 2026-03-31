<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion;

use ByCerfrance\LlmApiLib\Usage\UsageInterface;

interface CompletionResponseInterface extends CompletionInterface
{
    /**
     * Get usage of completion.
     *
     * @return UsageInterface
     */
    public function getUsage(): UsageInterface;

    /**
     * Get the finish reason of the preferred choice.
     *
     * @return FinishReason|null
     */
    public function getFinishReason(): ?FinishReason;
}
