<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;
use Psr\Log\LoggerInterface;

interface LlmInterface
{
    /**
     * Chat.
     *
     * @param string|CompletionInterface $completion
     * @param LoggerInterface|null $logger
     *
     * @return CompletionResponseInterface
     */
    public function chat(
        string|CompletionInterface $completion,
        ?LoggerInterface $logger = null,
    ): CompletionResponseInterface;

    /**
     * Get the maximum context window size in tokens, null if undefined.
     *
     * @return int|null
     */
    public function getMaxContextTokens(): ?int;

    /**
     * Get the maximum output tokens the model can generate, null if undefined.
     *
     * @return int|null
     */
    public function getMaxOutputTokens(): ?int;

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
