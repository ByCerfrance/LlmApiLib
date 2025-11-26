<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Message;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completion\Content\ContentInterface;
use Countable;
use IteratorAggregate;
use OutOfBoundsException;
use Override;
use Traversable;

class Choices implements MessageInterface, Countable, IteratorAggregate
{
    private readonly array $messages;
    private int $preferred;

    public function __construct(MessageInterface ...$messages)
    {
        $this->messages = array_values($messages);
        $this->preferred = 0;
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

    /**
     * Get preferred message.
     */
    public function getPreferred(): int
    {
        return $this->preferred;
    }

    /**
     * Set preferred message.
     *
     * @param int $preferred
     *
     * @return void
     */
    public function setPreferred(int $preferred): void
    {
        if (false === array_key_exists($preferred, $this->messages)) {
            throw new OutOfBoundsException('Preferred message does not exist');
        }

        $this->preferred = $preferred;
    }

    #[Override]
    public function getRole(): RoleEnum
    {
        return $this->messages[$this->preferred]->getRole();
    }

    #[Override]
    public function getContent(): ContentInterface
    {
        return $this->messages[$this->preferred]->getContent();
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return $this->messages[$this->preferred]->jsonSerialize();
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        return array_unique(
            array_merge(
                ...array_map(
                    fn(MessageInterface $message) => $message->requiredCapabilities(),
                    $this->messages,
                )
            ),
            SORT_REGULAR,
        );
    }
}
