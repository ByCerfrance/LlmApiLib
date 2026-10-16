# Streaming

[Back to documentation](README.md)

## Goal

Print text as it arrives, then access the full response. Use the [shared bootstrap](getting-started.md#shared-setup) and enable PHP's `curl` extension.

## Stream an answer

```php
<?php

declare(strict_types=1);

use ByCerfrance\LlmApiLib\Chat\Streaming\StreamingChat;
use ByCerfrance\LlmApiLib\Provider\OpenAi\StreamingOpenAi;
use ByCerfrance\LlmApiLib\Transport\CurlStreamingTransport;

require __DIR__ . '/bootstrap.php';

$provider = new StreamingOpenAi($apiKey, $client, new CurlStreamingTransport());
$chat = new StreamingChat($provider, $model);
$stream = $chat->stream('Explain crop rotation in three short sentences.');

try {
    foreach ($stream as $event) {
        if ($event->type === 'text.delta') {
            echo $event->text;
        }
        if ($event->response !== null) {
            echo PHP_EOL;
            echo 'History messages: ' . count($event->response->toConversation()) . PHP_EOL;
        }
    }
} finally {
    $stream->close();
}
```

Text appears in fragments. The final `completed` event carries the assembled ChatResponse. Without tools, its history contains the question and the answer: two messages.

The ordinary PSR-18 client remains available for synchronous calls. Streaming uses a separate cURL-multi transport because PSR-18 does not guarantee progressive delivery. Terminal, web server or reverse-proxy output buffering may separately affect what a human sees.

## Understand the events

| Type | Meaning |
| --- | --- |
| `text.delta` | Append this text fragment to the display |
| `tool.delta` | Informational fragment of tool arguments, not an executable call |
| `tool.started` | A complete tool invocation is about to execute |
| `tool.completed` | The tool result is available |
| `completed` | The final response, conversation and usage are available |

Supply a ChatRequest with tools as in the [tools guide](tools.md). Arguments are fully assembled and validated before execution; the next provider turn then continues streaming.

## SSE is framing, not JSON concatenation

The network may deliver half an event or several events in one chunk. The SSE decoder reconstructs event boundaries first. Each event's JSON is then interpreted. Text and argument **fields** may be concatenated; the JSON documents themselves are not concatenated.

The OpenAI stream must include a finish reason and `[DONE]`. Final usage may arrive in a separate usage-only chunk. Missing usage stays unknown.

## Stop early and handle failures

Keep the `finally` block even if you add `break` to the loop. `close()` immediately releases the active HTTP response and prevents subsequent tools/turns. It does not undo tool effects already completed. A cancelled stream has no fabricated final answer and cannot be consumed again.

RetryStreamingProvider retries transient failures only before the first event of the current provider turn. StreamingGuardChat checks the final response, but cannot retract text already shown. StreamingChatUsage records completed responses; place it inside a streaming guard to count rejected responses too.

Ordinary synchronous decorators do not advertise streaming. There is no streaming failover, native Gemini streaming or image streaming in these contracts.

Offline: `php examples/streaming.php` ([script](../examples/streaming.php)); add `--live` with OpenAI environment variables for a real request.

Related: [Retries and failover](retries-and-failover.md), [MCP](mcp.md).
