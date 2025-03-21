<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completions;

use ByCerfrance\LlmApiLib\Completions\Message\MessageInterface;
use Countable;
use IteratorAggregate;
use JsonSerializable;

interface CompletionsInterface extends JsonSerializable, Countable, IteratorAggregate
{
    /**
     * Get last message.
     *
     * @return MessageInterface|null
     */
    public function getLastMessage(): ?MessageInterface;

    /**
     * With new message.
     *
     * @param MessageInterface|string $message
     *
     * @return CompletionsInterface
     */
    public function withNewMessage(MessageInterface|string $message): CompletionsInterface;
}
