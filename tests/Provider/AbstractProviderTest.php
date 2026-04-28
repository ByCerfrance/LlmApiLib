<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponse;
use ByCerfrance\LlmApiLib\Completion\Content\ContentFactory;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\FinishReason;
use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\Choice;
use ByCerfrance\LlmApiLib\Completion\Message\Choices;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\MessageFactory;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\CostTier;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Model\QualityTier;
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
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Capability::class)]
#[UsesClass(Choice::class)]
#[UsesClass(Choices::class)]
#[UsesClass(Completion::class)]
#[UsesClass(CompletionResponse::class)]
#[UsesClass(ContentFactory::class)]
#[UsesClass(CostTier::class)]
#[UsesClass(FinishReason::class)]
#[UsesClass(Message::class)]
#[UsesClass(MessageFactory::class)]
#[UsesClass(ModelInfo::class)]
#[UsesClass(QualityTier::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(TextContent::class)]
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

    public function testChatParsesCachedTokensFromResponse(): void
    {
        $responseBody = json_encode([
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
                'total_tokens' => 150,
                'prompt_tokens_details' => [
                    'cached_tokens' => 80,
                ],
            ],
            'choices' => [
                [
                    'message' => ['role' => 'assistant', 'content' => 'Hello'],
                    'finish_reason' => 'stop',
                    'index' => 0,
                ],
            ],
        ]);

        $provider = new readonly class(
            'key',
            new ModelInfo('test-model'),
            $this->createClient($this->createResponse(200, $responseBody)),
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

        $response = $provider->chat(new Completion([]));

        self::assertSame(80, $response->getUsage()->getCachedTokens());
        self::assertSame(100, $response->getUsage()->getPromptTokens());
        self::assertSame(50, $response->getUsage()->getCompletionTokens());
        self::assertSame(150, $response->getUsage()->getTotalTokens());
    }

    public function testChatHandsMissingCachedTokensGracefully(): void
    {
        $responseBody = json_encode([
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
                'total_tokens' => 150,
            ],
            'choices' => [
                [
                    'message' => ['role' => 'assistant', 'content' => 'Hello'],
                    'finish_reason' => 'stop',
                    'index' => 0,
                ],
            ],
        ]);

        $provider = new readonly class(
            'key',
            new ModelInfo('test-model'),
            $this->createClient($this->createResponse(200, $responseBody)),
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

        $response = $provider->chat(new Completion([]));

        self::assertSame(0, $response->getUsage()->getCachedTokens());
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
                200 => 'OK',
                500 => 'Internal Server Error',
                default => 'Unknown',
            }
        );
        $response->method('getBody')->willReturn($stream);

        return $response;
    }
}
