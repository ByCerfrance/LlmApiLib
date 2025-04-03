<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

use Override;

class Usage implements UsageInterface
{
    public function __construct(
        private int $promptTokens = 0,
        private int $completionTokens = 0,
        private int $totalTokens = 0,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }

    public function addUsage(UsageInterface $usage): self
    {
        return $this->addTokens(
            promptTokens: $usage->getPromptTokens(),
            completionTokens: $usage->getCompletionTokens(),
            totalTokens: $usage->getTotalTokens(),
        );
    }

    public function addTokens(int $promptTokens, int $completionTokens, int $totalTokens): self
    {
        $this->promptTokens += $promptTokens;
        $this->completionTokens += $completionTokens;
        $this->totalTokens += $totalTokens;

        return $this;
    }

    #[Override]
    public function getPromptTokens(): int
    {
        return $this->promptTokens;
    }

    #[Override]
    public function getCompletionTokens(): int
    {
        return $this->completionTokens;
    }

    #[Override]
    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }
}
