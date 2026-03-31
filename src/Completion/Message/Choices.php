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
    private readonly array $choices;
    private int $preferred;

    public function __construct(Choice ...$choices)
    {
        $this->choices = array_values($choices);
        $this->preferred = 0;
    }

    #[Override]
    public function count(): int
    {
        return count($this->choices);
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->choices);
    }

    /**
     * Get preferred choice index.
     */
    public function getPreferred(): int
    {
        return $this->preferred;
    }

    /**
     * Set preferred choice index.
     *
     * @param int $preferred
     *
     * @return void
     *
     * @throws OutOfBoundsException
     */
    public function setPreferred(int $preferred): void
    {
        if (false === array_key_exists($preferred, $this->choices)) {
            throw new OutOfBoundsException('Preferred choice does not exist');
        }

        $this->preferred = $preferred;
    }

    /**
     * Get the preferred choice.
     *
     * @return Choice
     */
    public function getPreferredChoice(): Choice
    {
        return $this->choices[$this->preferred];
    }

    #[Override]
    public function getRole(): RoleEnum
    {
        return $this->choices[$this->preferred]->message->getRole();
    }

    #[Override]
    public function getContent(): ContentInterface
    {
        return $this->choices[$this->preferred]->message->getContent();
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return $this->choices[$this->preferred]->message->jsonSerialize();
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        return array_unique(
            array_merge(
                ...array_map(
                    fn(Choice $choice) => $choice->message->requiredCapabilities(),
                    $this->choices,
                )
            ),
            SORT_REGULAR,
        );
    }
}
