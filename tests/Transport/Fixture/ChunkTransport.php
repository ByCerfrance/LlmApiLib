<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Transport\Fixture;

use ByCerfrance\LlmApiLib\Transport\StreamingResponseInterface;
use ByCerfrance\LlmApiLib\Transport\StreamingTransportInterface;
use Override;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

class ChunkTransport implements StreamingTransportInterface
{
    public array $requests = [];

    public function __construct(private array $responses) {}

    #[Override]
    public function open(RequestInterface $request): StreamingResponseInterface
    {
        $this->requests[] = $request;
        return array_shift($this->responses) ?? throw new RuntimeException('Unexpected extra stream attempt.');
    }
}
