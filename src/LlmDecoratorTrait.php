<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;
use Override;

/**
 * Trait for LlmInterface decorators that delegate to a single inner provider.
 *
 * Classes using this trait must implement getProvider() and LlmInterface.
 */
trait LlmDecoratorTrait
{
    abstract public function getProvider(): LlmInterface;

    #[Override]
    public function getMaxContextTokens(): ?int
    {
        return $this->getProvider()->getMaxContextTokens();
    }

    #[Override]
    public function getMaxOutputTokens(): ?int
    {
        return $this->getProvider()->getMaxOutputTokens();
    }

    #[Override]
    public function getScoring(SelectionStrategy $strategy): float
    {
        return $this->getProvider()->getScoring($strategy);
    }

    #[Override]
    public function getUsage(): UsageInterface
    {
        return $this->getProvider()->getUsage();
    }

    #[Override]
    public function getCost(int $precision = 4): float
    {
        return $this->getProvider()->getCost($precision);
    }

    #[Override]
    public function getCapabilities(): array
    {
        return $this->getProvider()->getCapabilities();
    }

    #[Override]
    public function supports(Capability $capability, Capability ...$_capability): bool
    {
        return $this->getProvider()->supports($capability, ...$_capability);
    }

    #[Override]
    public function getLabels(): array
    {
        return $this->getProvider()->getLabels();
    }

    #[Override]
    public function getId(): string
    {
        return $this->getProvider()->getId();
    }
}
