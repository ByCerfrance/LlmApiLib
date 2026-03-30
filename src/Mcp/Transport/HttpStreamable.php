<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Mcp\Transport;

use Berlioz\Http\Message\Request;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * MCP transport using the Streamable HTTP protocol (JSON-only, no SSE).
 *
 * Sends JSON-RPC messages as HTTP POST requests to a single MCP endpoint.
 * Manages session lifecycle via the Mcp-Session-Id header.
 *
 * @see https://modelcontextprotocol.io/specification/2025-03-26/basic/transports#streamable-http
 */
class HttpStreamable implements TransportInterface
{
    private ?string $sessionId = null;

    /**
     * @param string $uri MCP endpoint URL (e.g. https://example.com/mcp)
     * @param ClientInterface $client PSR-18 HTTP client
     * @param array<string, string> $headers Additional HTTP headers (e.g. Authorization)
     */
    public function __construct(
        private readonly string $uri,
        private readonly ClientInterface $client,
        private readonly array $headers = [],
    ) {
    }

    #[Override]
    public function request(string $body): string
    {
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
            if ($statusCode !== 200 && $statusCode !== 202 && $statusCode !== 405) {
                throw new RuntimeException(
                    sprintf('MCP shutdown failed (%d %s)', $statusCode, $response->getReasonPhrase())
                );
            }
        } finally {
            $this->sessionId = null;
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
}
