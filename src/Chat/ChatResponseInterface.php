<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Chat\Conversation\ConversationInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Content\ContentInterface;
use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use InvalidArgumentException;

interface ChatResponseInterface
{
    /** @return ConversationInterface Complete history including the response. */
    public function toConversation(): ConversationInterface;

    /**
     * Continue the conversation with a new user message.
     *
     * Several contents form a single multipart message. The options of the original request are kept.
     *
     * @param MessageInterface|ContentInterface|string $content A complete message, or the first content of a user message.
     * @param MessageInterface|ContentInterface|string ...$contents Additional contents of the same user message.
     *
     * @return ChatRequestInterface Original request with the complete history and the new message.
     * @throws InvalidArgumentException When a complete message is combined with other contents.
     */
    public function reply(
        MessageInterface|ContentInterface|string $content,
        MessageInterface|ContentInterface|string ...$contents,
    ): ChatRequestInterface;

    /** @return string|null Text of the final answer, or null when it contains no text. */
    public function getText(): ?string;

    /** @return ChatResultInterface Final provider turn and its metadata. */
    public function getResult(): ChatResultInterface;

    /** @return list<ChatResultInterface> Provider turns executed during this chat call. */
    public function getTurns(): array;

    /** @return TokenUsageInterface Usage for this execution, not the lifetime of the executor. */
    public function getUsage(): TokenUsageInterface;
}
