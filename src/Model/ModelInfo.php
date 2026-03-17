<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Model;

use ByCerfrance\LlmApiLib\Usage\UsageInterface;
use Override;
use Stringable;

readonly class ModelInfo implements Stringable
{
    public array $capabilities;

    public function __construct(
        public string $name,
        array $capabilities = [],
        public QualityTier $qualityTier = QualityTier::GOOD,
        public CostTier $costTier = CostTier::MEDIUM,
        // Cost
        public float $inputCost = 0.0,
        public float $outputCost = 0.0,
        // Context window
        public ?int $maxContextTokens = null,
    ) {
        $this->capabilities = array_values(
            array_filter(
                $capabilities ?: Capability::defaults(),
                fn($v) => $v instanceof Capability,
            )
        );
    }

    #[Override]
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Compute the cost of the model.
     *
     * @param UsageInterface $usage
     * @param int $precision
     *
     * @return float
     */
    public function computeCost(UsageInterface $usage, int $precision = 4): float
    {
        return round(
            ($usage->getPromptTokens() * $this->inputCost / 1000000) +
            ($usage->getCompletionTokens() * $this->outputCost / 1000000),
            $precision,
        );
    }

    /**
     * Check if the model supports the given capabilities.
     *
     * @param Capability $capability
     * @param Capability ...$_capability
     *
     * @return bool
     */
    public function supports(Capability $capability, Capability ...$_capability): bool
    {
        $diff = array_udiff(
            [
                $capability,
                ...$_capability,
            ],
            $this->capabilities,
            fn(Capability $a, Capability $b) => strcmp($a->name, $b->name),
        );

        return true === empty($diff);
    }

    /**
     * Compute the base score for the model.
     *
     * @param SelectionStrategy $strategy
     *
     * @return float
     */
    public function baseScore(SelectionStrategy $strategy): float
    {
        $qualityScore = match ($this->qualityTier) {
            QualityTier::BASIC => .5,
            QualityTier::GOOD => 2.5,
            QualityTier::PREMIUM => 3.5,
        };
        $costScore = match ($this->costTier) {
            CostTier::LOW => 3.0,
            CostTier::MEDIUM => 2.0,
            CostTier::HIGH => 0.5,
        };

        return match ($strategy) {
            SelectionStrategy::CHEAP => ($costScore * 0.80) + ($qualityScore * 0.2),
            SelectionStrategy::BEST_QUALITY => ($qualityScore * 0.80) + ($costScore * 0.2),
            SelectionStrategy::BALANCED => ($qualityScore * 0.5) + ($costScore * 0.5),
        };
    }
}
