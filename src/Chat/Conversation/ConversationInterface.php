<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat\Conversation;

use ByCerfrance\LlmApiLib\Chat\Conversation\Message\MessageInterface;
use Countable;
use IteratorAggregate;

/**
 * An immutable history. Supplied messages and their contents must also be immutable.
 *
 * @extends IteratorAggregate<int, MessageInterface>
 */
interface ConversationInterface extends Countable, IteratorAggregate
{
    /**
     * @param MessageInterface|string $message Strings become user messages.
     * @return ConversationInterface A new independent history.
     */
    public function withNewMessage(MessageInterface|string $message): ConversationInterface;
}
