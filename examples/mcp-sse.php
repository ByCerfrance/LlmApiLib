<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Mcp\McpServer;
use ByCerfrance\LlmApiLib\Mcp\Transport\HttpStreamable;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAi;
use ByCerfrance\LlmApiLib\Transport\StreamingResponseInterface;
use ByCerfrance\LlmApiLib\Transport\StreamingTransportInterface;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$http = new class implements ClientInterface {
    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        if (isset($body['method'])) { return new Response('', 202); }
        $messages = $body['messages'];
        $last = $messages[array_key_last($messages)];
        $done = $last['role'] === 'tool';
        return new Response(json_encode(['choices' => [['message' => $done
            ? ['role' => 'assistant', 'content' => 'Double: ' . $last['content']]
            : ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'one', 'type' => 'function', 'function' => ['name' => 'double', 'arguments' => '{"value":21}']]]],
            'finish_reason' => $done ? 'stop' : 'tool_calls']]], JSON_THROW_ON_ERROR));
    }
};
$streaming = new class implements StreamingTransportInterface {
    #[Override]
    public function open(RequestInterface $request): StreamingResponseInterface
    {
        $rpc = json_decode((string) $request->getBody(), true);
        $result = match ($rpc['method']) {
            'initialize' => ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'serverInfo' => ['name' => 'offline', 'version' => '1']],
            'tools/list' => ['tools' => [['name' => 'double', 'description' => 'Double an integer', 'inputSchema' => ['type' => 'object', 'properties' => ['value' => ['type' => 'integer']], 'required' => ['value']]]]],
            'tools/call' => ['content' => [['type' => 'text', 'text' => (string) ($rpc['params']['arguments']['value'] * 2)]]],
        };
        $data = 'data: ' . json_encode(['jsonrpc' => '2.0', 'id' => $rpc['id'], 'result' => $result], JSON_THROW_ON_ERROR) . "\n\n";
        return new class($data) implements StreamingResponseInterface {
            private bool $closed = false;
            public function __construct(private string $data) {}
            #[Override]
            public function getStatusCode(): int { return 200; }
            #[Override]
            public function getHeaderLine(string $name): string { return strtolower($name) === 'content-type' ? 'text/event-stream' : ''; }
            #[Override]
            public function chunks(): iterable
            {
                foreach (str_split($this->data, 11) as $chunk) {
                    if ($this->closed) { return; }
                    yield $chunk;
                }
            }
            #[Override]
            public function close(): void { $this->closed = true; }
        };
    }
};
$server = new McpServer(new HttpStreamable('https://offline.test/mcp', $http, streamingTransport: $streaming), '2025-06-18');
try {
    $response = (new Chat(new OpenAi('unused', $http), new Model('demo')))->chat(new ChatRequest(new Conversation(['Double 21']), tools: $server));
    echo $response->getText() . PHP_EOL;
} finally {
    $server->shutdown();
}
