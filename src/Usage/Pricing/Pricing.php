<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage\Pricing;

use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use InvalidArgumentException;

/** Explicit token rates. Input measurements include cached input tokens. */
readonly class Pricing
{
    public function __construct(
        public string $currency,
        public ?float $input = null,
        public ?float $output = null,
        public ?float $cachedInput = null,
        public int $perTokens = 1000000,
    ) {
        if (trim($currency) === '' || $perTokens < 1) {
            throw new InvalidArgumentException('Pricing requires a currency and positive token unit size.');
        }
        foreach ([$input, $output, $cachedInput] as $rate) {
            if ($rate !== null && (!is_finite($rate) || $rate < 0)) {
                throw new InvalidArgumentException('Rates must be finite, non-negative or unknown.');
            }
        }
    }

    public function estimate(TokenUsageInterface $usage): Cost
    {
        $input = $usage->getPromptTokens();
        $cached = $usage->getCachedTokens();
        if ($input !== null && $cached !== null && $cached > $input) {
            throw new InvalidArgumentException('Cached tokens cannot exceed total input tokens.');
        }
        $inputCost = match (true) {
            $input === 0 => 0.0,
            $this->input !== null && $this->input === $this->cachedInput => $this->part($input, $this->input),
            $input === null || $cached === null => null,
            default => $this->combine($this->part($input - $cached, $this->input), $this->part($cached, $this->cachedInput)),
        };

        return new Cost($this->combine($inputCost, $this->part($usage->getCompletionTokens(), $this->output)), $this->currency);
    }

    private function part(?int $tokens, ?float $rate): ?float
    {
        return $tokens === 0 ? 0.0 : ($tokens === null || $rate === null ? null : $tokens * $rate / $this->perTokens);
    }

    private function combine(?float $first, ?float $second): ?float
    {
        return $first === null || $second === null ? null : $first + $second;
    }
}
