<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Transport;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

readonly class CurlStreamingTransport implements StreamingTransportInterface
{
    public function __construct(private int $timeoutMs = 120000, private int $connectTimeoutMs = 10000)
    {
        if ($timeoutMs < 1 || $connectTimeoutMs < 1) {
            throw new InvalidArgumentException('Streaming timeouts must be positive.');
        }
    }

    #[Override]
    public function open(RequestInterface $request): StreamingResponseInterface
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('The cURL extension is required for progressive HTTP streaming.');
        }
        return new CurlStreamingResponse($request, $this->timeoutMs, $this->connectTimeoutMs);
    }
}
