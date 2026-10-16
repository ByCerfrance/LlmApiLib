<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Transport\Fixture;

use ByCerfrance\LlmApiLib\Transport\StreamingResponseInterface;
use Override;

class ChunkResponse implements StreamingResponseInterface
{
    public bool $closed = false;
    public int $readCount = 0;

    public function __construct(private array $chunks, private int $status = 200, private array $headers = ['content-type' => 'text/event-stream']) {}

    #[Override]
    public function getStatusCode(): int { return $this->status; }

    #[Override]
    public function getHeaderLine(string $name): string { return $this->headers[strtolower($name)] ?? ''; }

    #[Override]
    public function chunks(): iterable
    {
        foreach ($this->chunks as $chunk) {
            if ($this->closed) { return; }
            $this->readCount++;
            yield $chunk;
        }
    }

    #[Override]
    public function close(): void { $this->closed = true; }
}
