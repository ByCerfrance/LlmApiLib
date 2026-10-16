<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Transport;

use Berlioz\Http\Message\Request;
use ByCerfrance\LlmApiLib\Transport\CurlStreamingResponse;
use ByCerfrance\LlmApiLib\Transport\CurlStreamingTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[CoversClass(CurlStreamingTransport::class)]
#[CoversClass(CurlStreamingResponse::class)]
#[RequiresPhpExtension('curl')]
#[RequiresFunction('proc_open')]
class CurlStreamingTransportTest extends TestCase
{
    public function testFirstBytesArriveBeforeServerProducesRemainingBody(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $signal = tempnam(sys_get_temp_dir(), 'llm-stream-');
        unlink($signal);
        $process = proc_open([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'zlib.output_compression=0', '-S', $address, __DIR__ . '/Fixture/server.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $response = null;
        try {
            $ready = false;
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $probe = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.05);
                if ($probe !== false) { fclose($probe); $ready = true; break; }
                usleep(10000);
            }
            $this->assertTrue($ready, 'Local fixture server must start.');
            $response = (new CurlStreamingTransport(8000))->open(new Request(method: 'GET', uri: 'http://' . $address . '/?signal=' . rawurlencode($signal)));
            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringStartsWith('text/event-stream', $response->getHeaderLine('Content-Type'));
            $body = '';
            foreach ($response->chunks() as $chunk) {
                $body .= $chunk;
                if (str_contains($body, "data: first\n\n")) { touch($signal); }
            }
            $this->assertStringContainsString('data: progressive', $body);
            $this->assertStringNotContainsString('data: buffered', $body);
            $response->close();
        } finally {
            $response?->close();
            proc_terminate($process);
            foreach ($pipes as $pipe) { fclose($pipe); }
            proc_close($process);
            if (is_file($signal)) { unlink($signal); }
        }
        // Destroy the response within this test so its destructor is not attributed to a later one.
        $response = null;
        gc_collect_cycles();
    }
}
