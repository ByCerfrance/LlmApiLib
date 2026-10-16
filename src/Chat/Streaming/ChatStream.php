<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat\Streaming;

use Closure;
use IteratorAggregate;
use Override;
use RuntimeException;
use Traversable;

/** @implements IteratorAggregate<int, ChatEvent> */
class ChatStream implements IteratorAggregate
{
    private bool $started = false;

    /** @param Closure(StreamControl): iterable<ChatEvent> $factory */
    public function __construct(private readonly Closure $factory, private readonly StreamControl $control = new StreamControl())
    {
    }

    #[Override]
    public function getIterator(): Traversable
    {
        if ($this->started) { throw new RuntimeException('A chat stream can only be consumed once.'); }
        $this->started = true;
        try {
            if ($this->control->isClosed()) { return; }
            foreach (($this->factory)($this->control) as $event) {
                if ($this->control->isClosed()) { return; }
                yield $event;
                if ($this->control->isClosed()) { return; }
            }
        } finally {
            $this->close();
        }
    }

    public function close(): void { $this->control->close(); }

    public function __destruct()
    {
        $this->close();
    }
}
