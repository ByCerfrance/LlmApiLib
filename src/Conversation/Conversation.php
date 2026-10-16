<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Conversation;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Completion\Message\UserMessage;
use Override;
use Traversable;

readonly class Conversation implements ConversationInterface
{
    /** @var list<MessageInterface> */
    private array $messages;

    /** @param iterable<MessageInterface|string> $messages */
    public function __construct(iterable $messages = [])
    {
        $normalized = [];
        foreach ($messages as $message) {
            $normalized[] = $this->normalizeMessage($message);
        }
        $this->messages = $normalized;
    }

    #[Override]
    public function withNewMessage(MessageInterface|string $message): ConversationInterface
    {
        return new self([...$this->messages, $message]);
    }

    #[Override]
    public function count(): int
    {
        return count($this->messages);
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->messages);
    }

    private function normalizeMessage(MessageInterface|string $message): MessageInterface
    {
        return is_string($message) ? new UserMessage($message) : $message;
    }
}
