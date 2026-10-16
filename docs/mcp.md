# MCP tools

[Back to documentation](README.md)

## Goal

Let a chat use tools exposed by a remote MCP server. The MCP client is a tool collection: Chat handles it like a collection of PHP tools.

## Connect a server

Use the [shared bootstrap](getting-started.md#shared-setup), enable ext-curl and set `MCP_URL` to the server's Streamable HTTP endpoint. Set `MCP_PROMPT` to a task supported by that server. Set `MCP_TOKEN` if the server requires bearer authentication.

```php
<?php

declare(strict_types=1);

use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Mcp\McpServer;
use ByCerfrance\LlmApiLib\Mcp\Transport\HttpStreamable;
use ByCerfrance\LlmApiLib\Transport\CurlStreamingTransport;

require __DIR__ . '/bootstrap.php';

$url = getenv('MCP_URL') ?: throw new RuntimeException('Set MCP_URL.');
$prompt = getenv('MCP_PROMPT') ?: throw new RuntimeException('Set MCP_PROMPT.');
$token = getenv('MCP_TOKEN');
$transport = new HttpStreamable(
    uri: $url,
    client: $client,
    headers: $token ? ['Authorization' => 'Bearer ' . $token] : [],
    streamingTransport: new CurlStreamingTransport(),
    notificationHandler: static function (array $notification): void {
        echo 'MCP notification: ' . $notification['method'] . PHP_EOL;
    },
);
$server = new McpServer($transport, protocolVersion: '2025-06-18');

try {
    $server->initialize();
    echo 'Available tools: ' . count($server) . PHP_EOL;
    $request = new ChatRequest(new Conversation([$prompt]), tools: $server);
    $response = $chat->chat($request);
    echo (string) $response->getResult()->getMessage()->getContent() . PHP_EOL;
} finally {
    $server->shutdown();
}
```

Initialization negotiates the protocol and discovers tools. It also happens lazily on first tool access; the explicit call above makes the lifecycle easier to see. The model decides which tools to request unless you configure tool choice.

Expected output depends on your server: a tool count, possibly notifications, and a final answer. For a deterministic example, `php examples/mcp-sse.php` prints **Double: 42** using offline fixtures ([script](../examples/mcp-sse.php)).

## JSON and SSE

With a streaming transport, HttpStreamable accepts either application/json or text/event-stream. SSE events carry complete JSON-RPC messages. Notifications are delivered to the callback; the response with the matching request ID completes the call. These messages are not concatenated into one result.

The progressive path supports negotiated versions 2025-03-26 and 2025-06-18. It includes the negotiated protocol version and any session ID on subsequent requests. Notifications and shutdown use the supplied PSR-18 client. Without a streaming transport, the existing JSON-only path remains available.

## Filter the exposed tools

Use this configuration variant before creating the ChatRequest:

```php
use ByCerfrance\LlmApiLib\Tool\FilteredToolCollection;

$tools = new FilteredToolCollection($server, ['get_weather', 'list_fields']);
$request = new ChatRequest(new Conversation([$prompt]), tools: $tools);
```

Replace the names with tools your server actually provides. Filtering changes the collection exposed to the model, not the server itself.

## Sessions and interruptions

The response stream closes after the correlated result/error or any failure. An expired progressive session is cleared. Explicitly shut down and reinitialize before starting fresh work. Interrupted tool requests are not automatically replayed: a remote effect may already have happened.

Background GET streams, Last-Event-ID reconnection, legacy HTTP+SSE endpoint discovery and server-initiated requests are not supported. SSE framing is shared with chat, but MCP message handling is separate.

Related: [Tools](tools.md), [Streaming](streaming.md), [OpenAPI](openapi.md).
