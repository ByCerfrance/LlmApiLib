<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Provider\AbstractProvider;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Usage\Usage;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(AbstractProvider::class)]
#[CoversClass(ProviderException::class)]
#[UsesClass(Capability::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ModelInfo::class)]
#[UsesClass(Usage::class)]
class AbstractProviderTest extends TestCase
{
    public function testChatThrowsProviderExceptionWithResponseBodyOnHttpError(): void
    {
        $provider = new readonly class(
            'key',
            new ModelInfo('test-model'),
            $this->createClient($this->createResponse(500, '{"error":"provider unavailable"}')),
            $this->createMock(RequestInterface::class),
        ) extends AbstractProvider {
            public function __construct(
                string $apiKey,
                ModelInfo $model,
                ClientInterface $client,
                private RequestInterface $request,
            ) {
                parent::__construct($apiKey, $model, $client);
            }

            #[Override]
            protected function createRequest(CompletionInterface $completion): RequestInterface
            {
                return $this->request;
            }

            #[Override]
            protected function createUri(CompletionInterface $completion): UriInterface
            {
                return Uri::createFromString('https://example.test/v1/chat/completions');
            }
        };

        try {
            $provider->chat(new Completion([]));
            self::fail('ProviderException was not thrown');
        } catch (ProviderException $exception) {
            self::assertSame('Invalid response (500 Internal Server Error)', $exception->getMessage());
            self::assertSame('{"error":"provider unavailable"}', $exception->getBody());
        }
    }

    private function createClient(ResponseInterface $response): ClientInterface
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        return $client;
    }

    private function createResponse(int $statusCode, string $body): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('getContents')->willReturn($body);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getReasonPhrase')->willReturn(
            match ($statusCode) {
                500 => 'Internal Server Error',
                default => 'Unknown',
            }
        );
        $response->method('getBody')->willReturn($stream);

        return $response;
    }
}
