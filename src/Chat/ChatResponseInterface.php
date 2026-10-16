<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Conversation\ConversationInterface;
use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;

interface ChatResponseInterface
{
    /** @return ConversationInterface Complete history including the response. */
    public function toConversation(): ConversationInterface;

    /** @return ChatResultInterface Final provider turn and its metadata. */
    public function getResult(): ChatResultInterface;

    /** @return list<ChatResultInterface> Provider turns executed during this chat call. */
    public function getTurns(): array;

    /** @return TokenUsageInterface Usage for this execution, not the lifetime of the executor. */
    public function getUsage(): TokenUsageInterface;
}
