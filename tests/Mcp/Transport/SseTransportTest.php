<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Mcp\Transport;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Mcp\Transport\HttpStreamable;
use ByCerfrance\LlmApiLib\Tests\Transport\Fixture\ChunkResponse;
use ByCerfrance\LlmApiLib\Tests\Transport\Fixture\ChunkTransport;
use ByCerfrance\LlmApiLib\Transport\SseDecoder;
use ByCerfrance\LlmApiLib\Transport\SseEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use RuntimeException;

#[CoversClass(HttpStreamable::class)]
#[UsesClass(SseDecoder::class)]
#[UsesClass(SseEvent::class)]
class SseTransportTest extends TestCase
{
    public function testNotificationsCorrelationNegotiationAndShutdown(): void
    {
        $init = new ChunkResponse(str_split("data: {\"jsonrpc\":\"2.0\",\"method\":\"notifications/progress\",\"params\":{}}\n\ndata: {\"jsonrpc\":\"2.0\",\"id\":1,\"result\":{\"protocolVersion\":\"2025-06-18\"}}\n\n", 5), headers: ['content-type' => 'text/event-stream', 'mcp-session-id' => 'session']);
        $json = new ChunkResponse(['{"jsonrpc":"2.0","id":2,"result":{}}'], headers: ['content-type' => 'application/json']);
        $stream = new ChunkTransport([$init, $json]);
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())->method('sendRequest')->willReturnCallback(function ($request): Response {
            $this->assertSame('DELETE', $request->getMethod());
            $this->assertSame('2025-06-18', $request->getHeaderLine('MCP-Protocol-Version'));
            return new Response('', 204);
        });
        $notifications = [];
        $transport = new HttpStreamable('https://example.test/mcp', $client, streamingTransport: $stream,
            notificationHandler: static function (array $message) use (&$notifications): void { $notifications[] = $message; });
        $transport->request('{"jsonrpc":"2.0","id":1,"method":"initialize"}');
        $transport->request('{"jsonrpc":"2.0","id":2,"method":"tools/list"}');
        $this->assertCount(1, $notifications);
        $this->assertSame('session', $stream->requests[1]->getHeaderLine('Mcp-Session-Id'));
        $this->assertSame('2025-06-18', $stream->requests[1]->getHeaderLine('MCP-Protocol-Version'));
        $this->assertTrue($init->closed);
        $this->assertTrue($json->closed);
        $transport->close();
        $this->assertNull($transport->getSessionId());
    }

    public function testInterruptedOrMismatchedResponsesCloseWithoutReplay(): void
    {
        foreach (["data: {\"jsonrpc\":\"2.0\",\"id\":9,\"result\":{}}\n\n", 'data: incomplete'] as $body) {
            $response = new ChunkResponse([$body]);
            $stream = new ChunkTransport([$response]);
            $transport = new HttpStreamable('https://example.test/mcp', $this->createStub(ClientInterface::class), streamingTransport: $stream);
            try {
                $transport->request('{"jsonrpc":"2.0","id":1,"method":"tools/call"}');
                $this->fail('Expected failure.');
            } catch (RuntimeException) {
                $this->assertTrue($response->closed);
                $this->assertCount(1, $stream->requests);
            }
        }
    }

    public function testExpiredSessionIsCleared(): void
    {
        $first = new ChunkResponse(['{"jsonrpc":"2.0","id":1,"result":{}}'], headers: ['content-type' => 'application/json', 'mcp-session-id' => 'session']);
        $expired = new ChunkResponse([], 404);
        $transport = new HttpStreamable('https://example.test/mcp', $this->createStub(ClientInterface::class), streamingTransport: new ChunkTransport([$first, $expired]));
        $transport->request('{"jsonrpc":"2.0","id":1,"method":"tools/list"}');
        try {
            $transport->request('{"jsonrpc":"2.0","id":2,"method":"tools/list"}');
            $this->fail('Expected expiration.');
        } catch (RuntimeException) {
            $this->assertNull($transport->getSessionId());
            $this->assertTrue($expired->closed);
        }
    }

    public function testUnsupportedNegotiationAndServerRequestsCloseTheStream(): void
    {
        foreach ([
            ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['protocolVersion' => 'unknown']],
            ['jsonrpc' => '2.0', 'id' => 'server', 'method' => 'sampling/createMessage'],
        ] as $message) {
            $response = new ChunkResponse(['data: ' . json_encode($message, JSON_THROW_ON_ERROR) . "\n\n"]);
            $transport = new HttpStreamable('https://example.test/mcp', $this->createStub(ClientInterface::class), streamingTransport: new ChunkTransport([$response]));
            try {
                $transport->request('{"jsonrpc":"2.0","id":1,"method":"initialize"}');
                $this->fail('Unsupported protocol behavior must fail.');
            } catch (RuntimeException) {
                $this->assertTrue($response->closed);
            }
        }
    }
}
