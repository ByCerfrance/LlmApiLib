<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Conversation\ConversationInterface;
use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use Override;

readonly class ChatResponse implements ChatResponseInterface
{
    /** @param list<ChatResultInterface> $turns */
    public function __construct(
        private ConversationInterface $conversation,
        private ChatResultInterface $result,
        private array $turns = [],
    ) {
    }

    #[Override]
    public function toConversation(): ConversationInterface
    {
        return $this->conversation;
    }

    #[Override]
    public function getResult(): ChatResultInterface
    {
        return $this->result;
    }

    #[Override]
    public function getUsage(): TokenUsageInterface
    {
        return TokenUsage::sum(...array_map(static fn(ChatResultInterface $turn): TokenUsageInterface => $turn->getUsage(), $this->getTurns()));
    }

    #[Override]
    public function getTurns(): array
    {
        return $this->turns ?: [$this->result];
    }
}
