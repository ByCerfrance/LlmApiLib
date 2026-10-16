<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Chat\Conversation\ConversationInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Content\ContentInterface;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use InvalidArgumentException;
use Override;

readonly class ChatResponse implements ChatResponseInterface
{
    /**
     * @param list<ChatResultInterface> $turns
     * @param ChatRequestInterface|null $request Request as supplied by the caller, reused by reply().
     */
    public function __construct(
        private ConversationInterface $conversation,
        private ChatResultInterface $result,
        private array $turns = [],
        private ?ChatRequestInterface $request = null,
    ) {
    }

    #[Override]
    public function toConversation(): ConversationInterface
    {
        return $this->conversation;
    }

    #[Override]
    public function reply(
        MessageInterface|ContentInterface|string $content,
        MessageInterface|ContentInterface|string ...$contents,
    ): ChatRequestInterface {
        if ($content instanceof MessageInterface) {
            if ($contents !== []) {
                throw new InvalidArgumentException('A complete message cannot be combined with other contents.');
            }
            $message = $content;
        } else {
            foreach ($contents as $part) {
                if ($part instanceof MessageInterface) {
                    throw new InvalidArgumentException('A complete message cannot be combined with other contents.');
                }
            }
            $message = new UserMessage($contents === [] ? $content : new ArrayContent($content, ...$contents));
        }
        $request = $this->request ?? new ChatRequest($this->conversation);

        return $request->withConversation($this->conversation->withNewMessage($message));
    }

    #[Override]
    public function getText(): ?string
    {
        $content = $this->result->getMessage()->getContent();
        $texts = [];
        foreach ($content instanceof ArrayContent ? $content : [$content] as $part) {
            if ($part instanceof TextContent) {
                $texts[] = $part->getContent();
            }
        }

        return $texts === [] ? null : implode('', $texts);
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
