<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Transport;

use CurlHandle;
use CurlMultiHandle;
use Override;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/** @internal cURL multi is pumped only as the consumer requests more bytes. */
class CurlStreamingResponse implements StreamingResponseInterface
{
    private CurlHandle $handle;
    private CurlMultiHandle $multi;
    private array $headers = [];
    private array $queue = [];
    private int $status = 0;
    private bool $headersReady = false;
    private bool $done = false;
    private bool $closed = false;
    private ?int $error = null;

    public function __construct(RequestInterface $request, int $timeoutMs, int $connectTimeoutMs)
    {
        $this->handle = curl_init((string) $request->getUri());
        $this->multi = curl_multi_init();
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) { $headers[] = $name . ': ' . $value; }
        }
        curl_setopt_array($this->handle, [
            CURLOPT_CUSTOMREQUEST => $request->getMethod(), CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => (string) $request->getBody(), CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $connectTimeoutMs, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_WRITEFUNCTION => function (CurlHandle $handle, string $bytes): int {
                $this->queue[] = $bytes;
                return strlen($bytes);
            },
            CURLOPT_HEADERFUNCTION => function (CurlHandle $handle, string $line): int {
                if (preg_match('~^HTTP/\S+\s+(\d+)~', $line, $match)) {
                    $this->status = (int) $match[1];
                    $this->headers = [];
                    $this->headersReady = false;
                } elseif (trim($line) === '' && $this->status >= 200) {
                    $this->headersReady = true;
                } elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $this->headers[strtolower(trim($name))][] = trim($value);
                }
                return strlen($line);
            },
        ]);
        curl_multi_add_handle($this->multi, $this->handle);
    }

    #[Override]
    public function getStatusCode(): int
    {
        while (!$this->headersReady && !$this->done && !$this->closed) { $this->pump(); }
        if (!$this->headersReady) { throw new RuntimeException('Streaming transport failed before response headers.'); }
        return $this->status;
    }

    #[Override]
    public function getHeaderLine(string $name): string
    {
        $this->getStatusCode();
        return implode(', ', $this->headers[strtolower($name)] ?? []);
    }

    #[Override]
    public function chunks(): iterable
    {
        try {
            while (!$this->closed) {
                while ($this->queue !== [] && !$this->closed) { yield array_shift($this->queue); }
                if ($this->done) {
                    if ($this->error !== null) { throw new RuntimeException('Streaming transport interrupted.', $this->error); }
                    return;
                }
                $this->pump();
            }
        } finally {
            $this->close();
        }
    }

    private function pump(): void
    {
        $code = curl_multi_exec($this->multi, $running);
        if ($code !== CURLM_OK) { throw new RuntimeException('Streaming transport pump failed.', $code); }
        while ($message = curl_multi_info_read($this->multi)) {
            if ($message['msg'] === CURLMSG_DONE) {
                $this->done = true;
                $this->error = $message['result'] === CURLE_OK ? null : $message['result'];
            }
        }
        if (!$this->done && $this->queue === [] && curl_multi_select($this->multi, 0.1) === -1) { usleep(1000); }
    }

    #[Override]
    public function close(): void
    {
        if (!$this->closed) {
            $this->closed = true;
            curl_multi_remove_handle($this->multi, $this->handle);
            // Release the callbacks: they reference this object and would delay its destruction to a GC cycle.
            curl_reset($this->handle);
            curl_close($this->handle);
            curl_multi_close($this->multi);
            $this->queue = [];
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
