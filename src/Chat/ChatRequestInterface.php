<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Completion\ReasoningEffort;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\ResponseFormatInterface;
use ByCerfrance\LlmApiLib\Completion\ServiceTier;
use ByCerfrance\LlmApiLib\Conversation\ConversationInterface;
use ByCerfrance\LlmApiLib\Model\CapabilityRequirement;

interface ChatRequestInterface extends CapabilityRequirement
{
    /** @return ConversationInterface Immutable input history. */
    public function getConversation(): ConversationInterface;

    /**
     * @param ConversationInterface $conversation
     * @return ChatRequestInterface
     */
    public function withConversation(ConversationInterface $conversation): ChatRequestInterface;

    /** @return int|null Null means inherit the executor/provider default. */
    public function getMaxTokens(): ?int;

    /**
     * @param int|null $maxTokens
     * @return ChatRequestInterface
     * @throws \InvalidArgumentException
     */
    public function withMaxTokens(?int $maxTokens): ChatRequestInterface;

    /** @return int|float|null Null means inherit the executor/provider default. */
    public function getTemperature(): int|float|null;

    /**
     * @param int|float|null $temperature
     * @return ChatRequestInterface
     * @throws \InvalidArgumentException
     */
    public function withTemperature(int|float|null $temperature): ChatRequestInterface;

    /** @return int|float|null Null means inherit the executor/provider default. */
    public function getTopP(): int|float|null;

    /**
     * @param int|float|null $topP
     * @return ChatRequestInterface
     * @throws \InvalidArgumentException
     */
    public function withTopP(int|float|null $topP): ChatRequestInterface;

    /** @return ResponseFormatInterface|null Requested structured/text output format. */
    public function getResponseFormat(): ?ResponseFormatInterface;

    /**
     * @param ResponseFormatInterface|null $responseFormat
     * @return ChatRequestInterface
     */
    public function withResponseFormat(?ResponseFormatInterface $responseFormat): ChatRequestInterface;

    /** @return ReasoningEffort|null Requested reasoning effort. */
    public function getReasoningEffort(): ?ReasoningEffort;

    /**
     * @param ReasoningEffort|null $reasoningEffort
     * @return ChatRequestInterface
     */
    public function withReasoningEffort(?ReasoningEffort $reasoningEffort): ChatRequestInterface;

    /** @return ServiceTier|null Requested processing tier. */
    public function getServiceTier(): ?ServiceTier;

    /**
     * @param ServiceTier|null $serviceTier
     * @return ChatRequestInterface
     */
    public function withServiceTier(?ServiceTier $serviceTier): ChatRequestInterface;

    /** @return int|null Requested sampling seed. */
    public function getSeed(): ?int;

    /**
     * @param int|null $seed
     * @return ChatRequestInterface
     */
    public function withSeed(?int $seed): ChatRequestInterface;
}
