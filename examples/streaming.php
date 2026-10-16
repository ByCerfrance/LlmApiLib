<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Chat\StreamingChat;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\StreamingOpenAi;
use ByCerfrance\LlmApiLib\Transport\CurlStreamingTransport;
use ByCerfrance\LlmApiLib\Transport\StreamingResponseInterface;
use ByCerfrance\LlmApiLib\Transport\StreamingTransportInterface;
use Override;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

require dirname(__DIR__) . '/vendor/autoload.php';

$live = in_array('--live', $argv, true);
$key = $live ? (getenv('OPENAI_API_KEY') ?: getenv('OPENAI_APIKEY') ?: throw new RuntimeException('Set OPENAI_API_KEY for live streaming.')) : 'unused-demo-key';
$transport = $live ? new CurlStreamingTransport() : new class implements StreamingTransportInterface {
    #[Override]
    public function open(RequestInterface $request): StreamingResponseInterface
    {
        return new class implements StreamingResponseInterface {
            private bool $closed = false;
            #[Override]
            public function getStatusCode(): int { return 200; }
            #[Override]
            public function getHeaderLine(string $name): string { return strtolower($name) === 'content-type' ? 'text/event-stream' : ''; }
            #[Override]
            public function chunks(): iterable
            {
                foreach (['Hello', ' from a stream.'] as $text) {
                    if ($this->closed) { return; }
                    yield 'data: ' . json_encode(['choices' => [['index' => 0, 'delta' => ['content' => $text]]]], JSON_THROW_ON_ERROR) . "\n\n";
                }
                yield "data: {\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n";
            }
            #[Override]
            public function close(): void { $this->closed = true; }
        };
    }
};
$chat = new StreamingChat(new StreamingOpenAi($key, new CurlAdapter(), $transport), new Model(getenv('OPENAI_MODEL') ?: 'gpt-4.1-mini'));
$stream = $chat->stream('Say hello briefly.');
try {
    foreach ($stream as $event) {
        if ($event->type === 'text.delta') { echo $event->text; }
        if ($event->response !== null) { echo PHP_EOL . 'Final history: ' . count($event->response->toConversation()) . ' messages' . PHP_EOL; }
    }
} finally {
    $stream->close();
}
