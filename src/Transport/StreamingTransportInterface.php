<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Transport;

use Psr\Http\Message\RequestInterface;

interface StreamingTransportInterface
{
    /** @param RequestInterface $request @return StreamingResponseInterface A non-buffering response. @throws \RuntimeException On transport failure. */
    public function open(RequestInterface $request): StreamingResponseInterface;
}
