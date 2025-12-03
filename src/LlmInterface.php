<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;

interface LlmInterface
{
    /**
     * Chat.
     *
     * @param string|CompletionInterface $completion
     *
     * @return CompletionResponseInterface
     */
    public function chat(string|CompletionInterface $completion): CompletionResponseInterface;

    /**
     * Get scoring for strategy.
     *
     * @param SelectionStrategy $strategy
     *
     * @return float
     */
    public function getScoring(SelectionStrategy $strategy): float;

    /**
     * Get usage.
     *
     * @return UsageInterface
     */
    public function getUsage(): UsageInterface;

    /**
     * Get cost.
     *
     * @param int $precision
     *
     * @return float
     */
    public function getCost(int $precision = 4): float;

    /**
     * Get capabilities.
     *
     * @return Capability[]
     */
    public function getCapabilities(): array;

    /**
     * Supports capability?
     *
     * @param Capability $capability
     * @param Capability ...$_capability
     *
     * @return bool
     */
    public function supports(Capability $capability, Capability ...$_capability): bool;
}
