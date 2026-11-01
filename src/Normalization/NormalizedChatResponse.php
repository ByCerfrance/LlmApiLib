<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\ChatResponseInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\ConversationInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Content\ContentInterface;
use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use Override;

/** Presents a normalized final result while continuation and accounting use the original response. */
readonly class NormalizedChatResponse implements ChatResponseInterface
{
    public function __construct(
        private ChatResponseInterface $originalResponse,
        private ChatResultInterface $result,
    ) {
    }

    public function getOriginalResponse(): ChatResponseInterface
    {
        return $this->originalResponse;
    }

    #[Override]
    public function toConversation(): ConversationInterface
    {
        return $this->originalResponse->toConversation();
    }

    #[Override]
    public function reply(
        MessageInterface|ContentInterface|string $content,
        MessageInterface|ContentInterface|string ...$contents,
    ): ChatRequestInterface {
        return $this->originalResponse->reply($content, ...$contents);
    }

    #[Override]
    public function getText(): ?string
    {
        return (new ChatResponse($this->toConversation(), $this->result))->getText();
    }

    #[Override]
    public function getResult(): ChatResultInterface
    {
        return $this->result;
    }

    /** @return list<ChatResultInterface> Original provider turns, before output normalization. */
    #[Override]
    public function getTurns(): array
    {
        return $this->originalResponse->getTurns();
    }

    #[Override]
    public function getUsage(): TokenUsageInterface
    {
        return $this->originalResponse->getUsage();
    }
}
