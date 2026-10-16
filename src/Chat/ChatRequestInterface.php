<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Conversation\ConversationInterface;

interface ChatRequestInterface
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
}
