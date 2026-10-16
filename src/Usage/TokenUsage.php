<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

use InvalidArgumentException;
use Override;

readonly class TokenUsage implements TokenUsageInterface
{
    /** Unknown in any usage makes the corresponding total unknown. */
    public static function sum(TokenUsageInterface $usage, TokenUsageInterface ...$usages): self
    {
        $totals = [0, 0, 0, 0];
        foreach ([$usage, ...$usages] as $usage) {
            foreach ([$usage->getPromptTokens(), $usage->getCompletionTokens(), $usage->getTotalTokens(), $usage->getCachedTokens()] as $index => $value) {
                $totals[$index] = $totals[$index] === null || $value === null ? null : $totals[$index] + $value;
            }
        }

        return new self(...$totals);
    }

    public function __construct(
        private ?int $promptTokens = null,
        private ?int $completionTokens = null,
        private ?int $totalTokens = null,
        private ?int $cachedTokens = null,
    ) {
        foreach ([$promptTokens, $completionTokens, $totalTokens, $cachedTokens] as $tokens) {
            if ($tokens !== null && $tokens < 0) {
                throw new InvalidArgumentException('Token counts must not be negative.');
            }
        }
    }

    #[Override]
    public function getPromptTokens(): ?int
    {
        return $this->promptTokens;
    }

    #[Override]
    public function getCompletionTokens(): ?int
    {
        return $this->completionTokens;
    }

    #[Override]
    public function getTotalTokens(): ?int
    {
        return $this->totalTokens;
    }

    #[Override]
    public function getCachedTokens(): ?int
    {
        return $this->cachedTokens;
    }
}
