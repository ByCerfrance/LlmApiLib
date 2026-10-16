<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Transport;

interface StreamingResponseInterface
{
    /** @return int HTTP status; may wait for headers. */
    public function getStatusCode(): int;
    /** @param string $name @return string Response header value. */
    public function getHeaderLine(string $name): string;
    /** @return iterable<string> Progressively received bytes. @throws \RuntimeException On transport interruption. */
    public function chunks(): iterable;
    /** @return void Release network resources immediately; idempotent. */
    public function close(): void;
}
