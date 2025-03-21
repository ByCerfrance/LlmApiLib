<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completions;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completions\Message\Message;
use ByCerfrance\LlmApiLib\Completions\Message\MessageInterface;
use Override;
use Traversable;

readonly class Completions implements CompletionsInterface
{
    private array $messages;

    public function __construct(
        MessageInterface ...$message,
    ) {
        $this->messages = $message;
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

    #[Override]
    public function jsonSerialize(): array
    {
        return $this->messages;
    }

    #[Override]
    public function getLastMessage(): ?MessageInterface
    {
        return $this->messages[count($this->messages) - 1];
    }

    #[Override]
    public function withNewMessage(MessageInterface|string $message): CompletionsInterface
    {
        if (is_string($message)) {
            $message = new Message($message);
        }

        return new Completions(...[...$this->messages, $message]);
    }
}
