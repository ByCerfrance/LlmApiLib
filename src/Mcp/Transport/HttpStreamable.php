<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Mcp\Transport;

use Berlioz\Http\Message\Request;
use ByCerfrance\LlmApiLib\Transport\SseDecoder;
use ByCerfrance\LlmApiLib\Transport\StreamingTransportInterface;
use Closure;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * MCP Streamable HTTP with optional progressive JSON/SSE delivery.
 *
 * Sends JSON-RPC messages as HTTP POST requests to a single MCP endpoint.
 * Manages session lifecycle via the Mcp-Session-Id header.
 *
 * @see https://modelcontextprotocol.io/specification/2025-03-26/basic/transports#streamable-http
 */
class HttpStreamable implements TransportInterface
{
    private ?string $sessionId = null;
    private ?string $negotiatedVersion = null;

    /**
     * @param string $uri MCP endpoint URL (e.g. https://example.com/mcp)
     * @param ClientInterface $client PSR-18 HTTP client
     * @param array<string, string> $headers Additional HTTP headers (e.g. Authorization)
     */
    public function __construct(
        private readonly string $uri,
        private readonly ClientInterface $client,
        private readonly array $headers = [],
        private readonly ?StreamingTransportInterface $streamingTransport = null,
        private readonly ?Closure $notificationHandler = null,
    ) {
    }

    #[Override]
    public function request(string $body): string
    {
        if ($this->streamingTransport !== null) {
            return $this->requestProgressively($body);
        }
        $response = $this->sendHttpPost($body);

        $this->captureSessionId($response);

        $contentType = $this->parseContentType($response);

        if (str_starts_with($contentType, 'text/event-stream')) {
            throw new RuntimeException(
                'MCP server returned SSE stream (text/event-stream), which is not supported by this client. '
                . 'Only JSON responses (application/json) are supported.'
            );
        }

        if (!str_starts_with($contentType, 'application/json')) {
            throw new RuntimeException(
                sprintf('Unexpected MCP response content type: %s', $contentType)
            );
        }

        return $response->getBody()->getContents();
    }

    #[Override]
    public function notify(string $body): void
    {
        $response = $this->sendHttpPost($body);

        $this->captureSessionId($response);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200 && $statusCode !== 202) {
            throw new RuntimeException(
                sprintf('MCP notification failed (%d %s)', $statusCode, $response->getReasonPhrase())
            );
        }
    }

    #[Override]
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    #[Override]
    public function close(): void
    {
        if (null === $this->sessionId) {
            $this->negotiatedVersion = null;
            return;
        }

        try {
            $request = new Request(
                method: 'DELETE',
                uri: $this->uri,
                headers: $this->buildHeaders(),
            );
            $response = $this->client->sendRequest($request);

            // 405 = server does not support client-initiated shutdown, that's OK
            $statusCode = $response->getStatusCode();
            if (!in_array($statusCode, [200, 202, 204, 404, 405], true)) {
                throw new RuntimeException(
                    sprintf('MCP shutdown failed (%d %s)', $statusCode, $response->getReasonPhrase())
                );
            }
        } finally {
            $this->sessionId = null;
            $this->negotiatedVersion = null;
        }
    }

    /**
     * Send an HTTP POST request to the MCP endpoint.
     */
    private function sendHttpPost(string $body): ResponseInterface
    {
        $request = new Request(
            method: Request::HTTP_METHOD_POST,
            uri: $this->uri,
            body: $body,
            headers: array_merge(
                $this->buildHeaders(),
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json, text/event-stream',
                ],
            ),
        );

        $response = $this->client->sendRequest($request);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            if ($statusCode === 404 && null !== $this->sessionId) {
                if ($this->streamingTransport !== null) {
                    $this->sessionId = null;
                    $this->negotiatedVersion = null;
                }
                throw new RuntimeException(
                    'MCP session expired (404). Client should re-initialize with a new session.'
                );
            }

            throw new RuntimeException(
                sprintf('MCP HTTP error (%d %s)', $statusCode, $response->getReasonPhrase())
            );
        }

        return $response;
    }

    /**
     * Build HTTP headers including session ID if available.
     *
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        $headers = $this->headers;

        if (null !== $this->sessionId) {
            $headers['Mcp-Session-Id'] = $this->sessionId;
        }
        if ($this->negotiatedVersion !== null) {
            $headers['MCP-Protocol-Version'] = $this->negotiatedVersion;
        }

        return $headers;
    }

    /**
     * Capture the Mcp-Session-Id header from the response if present.
     */
    private function captureSessionId(ResponseInterface $response): void
    {
        $header = $response->getHeader('Mcp-Session-Id');
        if (!empty($header)) {
            $this->sessionId = $header[0];
        }
    }

    /**
     * Parse Content-Type header from response, stripping any charset/parameters.
     */
    private function parseContentType(ResponseInterface $response): string
    {
        $header = $response->getHeaderLine('Content-Type');
        $parts = explode(';', $header, 2);

        return trim($parts[0]);
    }

    private function requestProgressively(string $body): string
    {
        $rpc = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($rpc) || ($rpc['jsonrpc'] ?? null) !== '2.0' || !isset($rpc['id'])
            || (!is_int($rpc['id']) && !is_string($rpc['id']))) {
            throw new RuntimeException('Expected a JSON-RPC request with an ID.');
        }
        $response = $this->streamingTransport->open(new Request(method: 'POST', uri: $this->uri, body: $body,
            headers: [...$this->buildHeaders(), 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream']));
        try {
            $status = $response->getStatusCode();
            if ($status === 404 && $this->sessionId !== null) {
                $this->sessionId = null;
                $this->negotiatedVersion = null;
                throw new RuntimeException('MCP session expired (404). Explicit shutdown and re-initialization required.');
            }
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException(sprintf('MCP HTTP error (%d).', $status));
            }
            $session = $response->getHeaderLine('Mcp-Session-Id');
            if ($session !== '') { $this->sessionId = $session; }
            $type = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'), 2)[0]));
            if ($type === 'text/event-stream') {
                foreach ((new SseDecoder())->events($response->chunks()) as $event) {
                    if ($event->event !== 'message') { continue; }
                    $message = json_decode($event->data, true, flags: JSON_THROW_ON_ERROR);
                    if ($this->correlate($message, $rpc)) { return $event->data; }
                }
                throw new RuntimeException('MCP SSE interrupted before the correlated response. Request was not replayed.');
            }
            if ($type !== 'application/json') { throw new RuntimeException('Unexpected MCP response content type.'); }
            $json = '';
            foreach ($response->chunks() as $chunk) {
                $json .= $chunk;
                if (strlen($json) > 16777216) { throw new RuntimeException('MCP JSON response exceeds size limit.'); }
            }
            if (!$this->correlate(json_decode($json, true, flags: JSON_THROW_ON_ERROR), $rpc)) {
                throw new RuntimeException('Expected a correlated JSON-RPC response.');
            }
            return $json;
        } finally {
            $response->close();
        }
    }

    private function correlate(mixed $message, array $request): bool
    {
        if (!is_array($message) || ($message['jsonrpc'] ?? null) !== '2.0') {
            throw new RuntimeException('Invalid MCP JSON-RPC message.');
        }
        if (isset($message['method'])) {
            if (!is_string($message['method']) || array_key_exists('result', $message) || array_key_exists('error', $message)) {
                throw new RuntimeException('Invalid MCP notification envelope.');
            }
            if (array_key_exists('id', $message)) {
                throw new RuntimeException('Server-initiated MCP requests are not supported.');
            }
            if ($this->notificationHandler !== null) { ($this->notificationHandler)($message); }
            return false;
        }
        if (($message['id'] ?? null) !== $request['id']
            || array_key_exists('result', $message) === array_key_exists('error', $message)) {
            throw new RuntimeException('MCP response ID or result/error envelope is invalid.');
        }
        if (($request['method'] ?? null) === 'initialize' && isset($message['result'])) {
            $version = $message['result']['protocolVersion'] ?? null;
            if (!in_array($version, ['2025-03-26', '2025-06-18'], true)) {
                throw new RuntimeException('Unsupported negotiated MCP protocol version.');
            }
            $this->negotiatedVersion = $version;
        }
        return true;
    }
}
