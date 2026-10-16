<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use Closure;

class StreamControl
{
    private bool $closed = false;
    /** @var array<int, Closure(): void> */
    private array $closers = [];
    private int $nextId = 0;

    public function isClosed(): bool { return $this->closed; }

    public function onClose(Closure $close): int
    {
        $id = $this->nextId++;
        if ($this->closed) { $close(); } else { $this->closers[$id] = $close; }
        return $id;
    }

    public function remove(int $id): void { unset($this->closers[$id]); }

    public function close(): void
    {
        if ($this->closed) { return; }
        $this->closed = true;
        $closers = $this->closers;
        $this->closers = [];
        foreach ($closers as $close) { $close(); }
    }
}
