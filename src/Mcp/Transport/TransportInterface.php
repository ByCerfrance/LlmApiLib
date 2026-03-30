<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Mcp\Transport;

use RuntimeException;

/**
 * Transport layer for MCP client-server communication.
 *
 * Abstracts the underlying communication mechanism (HTTP Streamable, stdio, etc.)
 * so that the MCP protocol layer (McpServer) remains transport-agnostic.
 *
 * @see https://modelcontextprotocol.io/specification/2025-03-26/basic/transports
 */
interface TransportInterface
{
    /**
     * Send a JSON-RPC request and return the raw response body.
     *
     * @param string $body JSON-RPC request body (JSON string)
     *
     * @return string Raw response body (JSON string)
     * @throws RuntimeException On transport error
     */
    public function request(string $body): string;

    /**
     * Send a JSON-RPC notification (no response body expected).
     *
     * @param string $body JSON-RPC notification body (JSON string)
     *
     * @throws RuntimeException On transport error
     */
    public function notify(string $body): void;

    /**
     * Get the session ID assigned by the server, if any.
     *
     * For HTTP Streamable transport, this is the Mcp-Session-Id header value.
     * For transports that do not support sessions (e.g. stdio), returns null.
     *
     * @return string|null
     */
    public function getSessionId(): ?string;

    /**
     * Close the transport connection.
     *
     * For HTTP Streamable transport, this sends an HTTP DELETE to terminate the session.
     * For stdio transport, this closes stdin and terminates the subprocess.
     */
    public function close(): void;
}
