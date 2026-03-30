<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Mcp\Transport;

use ByCerfrance\LlmApiLib\Mcp\Transport\HttpStreamable;
use ByCerfrance\LlmApiLib\Mcp\Transport\TransportInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

#[CoversClass(HttpStreamable::class)]
class HttpStreamableTest extends TestCase
{
    /**
     * Create a mock PSR-7 Response.
     */
    private function createResponse(
        int $statusCode = 200,
        string $body = '',
        array $headers = [],
    ): ResponseInterface {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);
        $stream->method('__toString')->willReturn($body);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getReasonPhrase')->willReturn(match (true) {
            $statusCode === 200 => 'OK',
            $statusCode === 202 => 'Accepted',
            $statusCode === 400 => 'Bad Request',
            $statusCode === 404 => 'Not Found',
            $statusCode === 405 => 'Method Not Allowed',
            $statusCode === 500 => 'Internal Server Error',
            default => 'Unknown',
        });
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaderLine')
            ->willReturnCallback(fn(string $name): string => $headers[$name] ?? '');
        $response->method('getHeader')
            ->willReturnCallback(fn(string $name): array => isset($headers[$name]) ? [$headers[$name]] : []);

        return $response;
    }

    /**
     * Create a mock PSR-18 client that captures requests and returns responses.
     */
    private function createCapturingClient(array $responses, array &$captured): ClientInterface
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(count($responses)))
            ->method('sendRequest')
            ->willReturnCallback(function (RequestInterface $req) use (&$responses, &$captured): ResponseInterface {
                $captured[] = $req;
                return array_shift($responses);
            });

        return $client;
    }

    // -----------------------------------------------------------------------
    // TransportInterface compliance
    // -----------------------------------------------------------------------

    public function testImplementsTransportInterface(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    // -----------------------------------------------------------------------
    // request() tests
    // -----------------------------------------------------------------------

    public function testRequestReturnsJsonBody(): void
    {
        $jsonBody = '{"jsonrpc":"2.0","id":1,"result":{"tools":[]}}';
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(body: $jsonBody, headers: ['Content-Type' => 'application/json']),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);
        $result = $transport->request('{"jsonrpc":"2.0","id":1,"method":"tools/list"}');

        $this->assertSame($jsonBody, $result);
    }

    public function testRequestSendsPostWithCorrectHeaders(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(body: '{"jsonrpc":"2.0","id":1,"result":{}}', headers: ['Content-Type' => 'application/json']),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);
        $transport->request('{"test":true}');

        $this->assertCount(1, $captured);
        $req = $captured[0];
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('https://mcp.example.com/mcp', (string)$req->getUri());
        $this->assertSame(['application/json'], $req->getHeader('Content-Type'));
        $this->assertSame(['application/json, text/event-stream'], $req->getHeader('Accept'));
    }

    public function testRequestSendsCustomHeaders(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(body: '{"jsonrpc":"2.0","id":1,"result":{}}', headers: ['Content-Type' => 'application/json']),
        ], $captured);

        $transport = new HttpStreamable(
            uri: 'https://mcp.example.com/mcp',
            client: $client,
            headers: ['Authorization' => 'Bearer my-token', 'X-Custom' => 'value'],
        );
        $transport->request('{}');

        $req = $captured[0];
        $this->assertSame(['Bearer my-token'], $req->getHeader('Authorization'));
        $this->assertSame(['value'], $req->getHeader('X-Custom'));
    }

    public function testRequestThrowsOnSseResponse(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(body: 'data: {}', headers: ['Content-Type' => 'text/event-stream']),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSE stream (text/event-stream), which is not supported');
        $transport->request('{}');
    }

    public function testRequestThrowsOnUnexpectedContentType(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(body: '<html>', headers: ['Content-Type' => 'text/html']),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected MCP response content type: text/html');
        $transport->request('{}');
    }

    public function testRequestThrowsOnHttpError(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(statusCode: 500),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MCP HTTP error (500 Internal Server Error)');
        $transport->request('{}');
    }

    public function testRequestThrowsOnSessionExpired404(): void
    {
        $captured = [];
        // First request: initialize (sets session)
        // Second request: 404 (session expired)
        $client = $this->createCapturingClient([
            $this->createResponse(
                body: '{"jsonrpc":"2.0","id":1,"result":{}}',
                headers: ['Content-Type' => 'application/json', 'Mcp-Session-Id' => 'sess-123'],
            ),
            $this->createResponse(statusCode: 404),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);

        // First request captures session ID
        $transport->request('{}');
        $this->assertSame('sess-123', $transport->getSessionId());

        // Second request gets 404 with active session → session expired
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MCP session expired (404)');
        $transport->request('{}');
    }

    public function testRequestHandlesContentTypeWithCharset(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(
                body: '{"jsonrpc":"2.0","id":1,"result":{}}',
                headers: ['Content-Type' => 'application/json; charset=utf-8'],
            ),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);
        $result = $transport->request('{}');

        $this->assertSame('{"jsonrpc":"2.0","id":1,"result":{}}', $result);
    }

    // -----------------------------------------------------------------------
    // Session ID management
    // -----------------------------------------------------------------------

    public function testSessionIdCapturedOnRequest(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(
                body: '{"jsonrpc":"2.0","id":1,"result":{}}',
                headers: ['Content-Type' => 'application/json', 'Mcp-Session-Id' => 'my-session'],
            ),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);
        $this->assertNull($transport->getSessionId());

        $transport->request('{}');
        $this->assertSame('my-session', $transport->getSessionId());
    }

    public function testSessionIdCapturedOnNotify(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(statusCode: 202, headers: ['Mcp-Session-Id' => 'notify-session']),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);
        $transport->notify('{}');

        $this->assertSame('notify-session', $transport->getSessionId());
    }

    public function testSessionIdSentOnSubsequentRequests(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            // First: response with session ID
            $this->createResponse(
                body: '{"jsonrpc":"2.0","id":1,"result":{}}',
                headers: ['Content-Type' => 'application/json', 'Mcp-Session-Id' => 'sess-42'],
            ),
            // Second: subsequent request
            $this->createResponse(
                body: '{"jsonrpc":"2.0","id":2,"result":{}}',
                headers: ['Content-Type' => 'application/json'],
            ),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);
        $transport->request('{}');
        $transport->request('{}');

        // First request should NOT have session ID
        $this->assertEmpty($captured[0]->getHeader('Mcp-Session-Id'));

        // Second request SHOULD have session ID
        $this->assertSame(['sess-42'], $captured[1]->getHeader('Mcp-Session-Id'));
    }

    public function testNoSessionIdWhenServerDoesNotProvideOne(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(
                body: '{"jsonrpc":"2.0","id":1,"result":{}}',
                headers: ['Content-Type' => 'application/json'],
            ),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);
        $transport->request('{}');

        $this->assertNull($transport->getSessionId());
    }

    // -----------------------------------------------------------------------
    // notify() tests
    // -----------------------------------------------------------------------

    public function testNotifyAccepts202(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(statusCode: 202),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);
        $transport->notify('{"jsonrpc":"2.0","method":"notifications/initialized"}');

        $this->assertCount(1, $captured);
        $this->assertSame('POST', $captured[0]->getMethod());
    }

    public function testNotifyAccepts200(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(statusCode: 200),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);
        $transport->notify('{}'); // Should not throw
    }

    public function testNotifyThrowsOnUnexpectedStatus(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(statusCode: 400),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MCP HTTP error (400 Bad Request)');
        $transport->notify('{}');
    }

    // -----------------------------------------------------------------------
    // close() tests
    // -----------------------------------------------------------------------

    public function testCloseSendsDeleteWithSessionId(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            // First: request that sets session ID
            $this->createResponse(
                body: '{"jsonrpc":"2.0","id":1,"result":{}}',
                headers: ['Content-Type' => 'application/json', 'Mcp-Session-Id' => 'close-session'],
            ),
            // Second: DELETE response
            $this->createResponse(statusCode: 200),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);
        $transport->request('{}');
        $transport->close();

        $this->assertCount(2, $captured);
        $deleteReq = $captured[1];
        $this->assertSame('DELETE', $deleteReq->getMethod());
        $this->assertSame(['close-session'], $deleteReq->getHeader('Mcp-Session-Id'));
    }

    public function testCloseHandles405Silently(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(
                body: '{"jsonrpc":"2.0","id":1,"result":{}}',
                headers: ['Content-Type' => 'application/json', 'Mcp-Session-Id' => 'sess'],
            ),
            $this->createResponse(statusCode: 405),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);
        $transport->request('{}');
        $transport->close(); // Should not throw

        $this->assertNull($transport->getSessionId());
    }

    public function testCloseWithoutSessionIsNoop(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);
        $transport->close(); // No session, no-op
    }

    public function testCloseResetsSessionId(): void
    {
        $captured = [];
        $client = $this->createCapturingClient([
            $this->createResponse(
                body: '{"jsonrpc":"2.0","id":1,"result":{}}',
                headers: ['Content-Type' => 'application/json', 'Mcp-Session-Id' => 'sess'],
            ),
            $this->createResponse(statusCode: 200),
        ], $captured);

        $transport = new HttpStreamable('https://mcp.example.com/mcp', $client);
        $transport->request('{}');
        $this->assertSame('sess', $transport->getSessionId());

        $transport->close();
        $this->assertNull($transport->getSessionId());
    }
}
