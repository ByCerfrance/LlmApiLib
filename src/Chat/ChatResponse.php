<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Conversation\ConversationInterface;
use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use Override;

readonly class ChatResponse implements ChatResponseInterface
{
    public function __construct(
        private ConversationInterface $conversation,
        private ChatResultInterface $result,
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
        return $this->result->getUsage();
    }
}
