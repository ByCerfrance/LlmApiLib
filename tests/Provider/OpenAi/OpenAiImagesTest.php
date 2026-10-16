<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider\OpenAi;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Image\FailoverImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Image\ImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Image\ImageResponse;
use ByCerfrance\LlmApiLib\Image\ImageUsage;
use ByCerfrance\LlmApiLib\Image\RetryImageGenerator;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\ImageResult;
use ByCerfrance\LlmApiLib\Provider\JsonTransport;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAiImages;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Provider\ProviderImageRequest;
use ByCerfrance\LlmApiLib\Resilience\RetryPolicy;
use ByCerfrance\LlmApiLib\Resilience\RetryRunner;
use ByCerfrance\LlmApiLib\Usage\Measurement;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use ByCerfrance\LlmApiLib\Usage\UsageAccumulator;
use ByCerfrance\LlmApiLib\Usage\UsageCollector;
use ByCerfrance\LlmApiLib\Usage\UsageComposite;
use ByCerfrance\LlmApiLib\Usage\UsageRecord;
use ByCerfrance\LlmApiLib\Usage\UsageSnapshot;
use ByCerfrance\LlmApiLib\Usage\UsageTotal;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(OpenAiImages::class)]
#[CoversClass(ImageGenerator::class)]
#[CoversClass(ImageRequest::class)]
#[CoversClass(ImageResponse::class)]
#[CoversClass(ImageResult::class)]
#[CoversClass(ImageAsset::class)]
#[CoversClass(RetryImageGenerator::class)]
#[CoversClass(FailoverImageGenerator::class)]
#[CoversClass(ImageUsage::class)]
#[UsesClass(JsonTransport::class)]
#[UsesClass(Model::class)]
#[UsesClass(Capability::class)]
#[UsesClass(ProviderImageRequest::class)]
#[UsesClass(TokenUsage::class)]
#[UsesClass(FailureKind::class)]
#[UsesClass(ProviderException::class)]
#[UsesClass(RetryPolicy::class)]
#[UsesClass(RetryRunner::class)]
#[UsesClass(Measurement::class)]
#[UsesClass(UsageRecord::class)]
#[UsesClass(UsageCollector::class)]
#[UsesClass(UsageAccumulator::class)]
#[UsesClass(UsageComposite::class)]
#[UsesClass(UsageSnapshot::class)]
#[UsesClass(UsageTotal::class)]
class OpenAiImagesTest extends TestCase
{
    public function testImageUrlsAndMissingUsageArePreserved(): void
    {
        $requests = [];
        $generator = new ImageGenerator(new OpenAiImages('key', $this->client([new Response('{"data":[{"url":"https://example.test/image.png"}]}')], $requests)), new Model('custom'));
        $response = $generator->generate('Draw');
        $this->assertSame('https://example.test/image.png', $response->getImages()[0]->url);
        $this->assertNull($response->getImages()[0]->mimeType);
        $this->assertNull($response->getUsage()->getTotalTokens());
    }

    public function testStandaloneMultipleImagesAndUsage(): void
    {
        $requests = [];
        $response = (new ImageGenerator(new OpenAiImages('key', $this->client([$this->images()], $requests)), new Model('gpt-image-1')))->generate(new ImageRequest('A farm', 2, ['output_format' => 'png']));
        $this->assertCount(2, $response->getImages());
        $this->assertSame('first', $response->getImages()[0]->getBytes());
        $this->assertSame('image/png', $response->getImages()[0]->mimeType);
        $this->assertSame('https://api.openai.com/v1/images/generations', $requests[0]['uri']);
        $this->assertSame('Bearer key', $requests[0]['authorization']);
        $this->assertSame(2, $requests[0]['body']['n']);
        $this->assertSame('gpt-image-1', $requests[0]['body']['model']);
        $this->assertSame(15, $response->getUsage()->getTotalTokens());
    }

    public function testImageRetryFailoverAndUsageBoundaries(): void
    {
        $a = [];
        $b = [];
        $collector = new UsageCollector();
        $first = new ImageGenerator(new OpenAiImages('key', $this->client([new Response('', 503), new Response('', 503)], $a)), new Model('gpt-image-1'));
        $second = new ImageGenerator(new OpenAiImages('key', $this->client([$this->images()], $b)), new Model('gpt-image-1'));
        $generator = new FailoverImageGenerator(new RetryImageGenerator(new ImageUsage($first, $collector), new RetryPolicy(2, 0)), new ImageUsage($second, $collector));
        $generator->generate('Draw');
        $this->assertSame(3, $collector->snapshot()->recordCount);
        $this->assertNull($collector->snapshot()->get('total', 'token')->getValue());
        $this->assertSame(15, $collector->snapshot()->get('total', 'token')->knownValue);
        $this->assertSame(2, $collector->snapshot()->get('output', 'image')->knownValue);
    }

    public function testAspectRatioIsTranslatedToASupportedSize(): void
    {
        $requests = [];
        $generator = new ImageGenerator(new OpenAiImages('key', $this->client([$this->images(), $this->images()], $requests)), new Model('gpt-image-1'));
        $generator->generate(new ImageRequest('Draw', aspectRatio: '3:2'));
        $generator->generate(new ImageRequest('Draw', aspectRatio: '1:1'));
        $this->assertSame('1536x1024', $requests[0]['body']['size']);
        $this->assertSame('1024x1024', $requests[1]['body']['size']);
    }

    /** @return iterable<string, array{string, ImageRequest}> */
    public static function unsupportedAspectRatios(): iterable
    {
        yield 'unknown ratio' => ['gpt-image-1', new ImageRequest('Draw', aspectRatio: '16:9')];
        yield 'model-specific ratio' => ['dall-e-2', new ImageRequest('Draw', aspectRatio: '3:2')];
        yield 'conflicting size' => ['gpt-image-1', new ImageRequest('Draw', options: ['size' => '1024x1024'], aspectRatio: '1:1')];
    }

    #[DataProvider('unsupportedAspectRatios')]
    public function testUnsupportedAspectRatiosFailBeforeHttp(string $model, ImageRequest $request): void
    {
        $requests = [];
        $generator = new ImageGenerator(new OpenAiImages('key', $this->client([], $requests)), new Model($model));
        $this->expectException(InvalidArgumentException::class);
        $generator->generate($request);
    }

    public function testGptImageRejectsLegacyResponseFormat(): void
    {
        $requests = [];
        $generator = new ImageGenerator(new OpenAiImages('key', $this->client([], $requests)), new Model('gpt-image-1'));
        $this->expectException(InvalidArgumentException::class);
        $generator->generate(new ImageRequest('Draw', options: ['response_format' => 'url']));
    }

    public function testMalformedImageDataIsNormalized(): void
    {
        $requests = [];
        $generator = new ImageGenerator(new OpenAiImages('key', $this->client([new Response('{"data":[{"b64_json":"!!!"}]}')], $requests)), new Model('gpt-image-1'));
        try {
            $generator->generate('Draw');
            $this->fail('Invalid base64 must fail.');
        } catch (ProviderException $failure) {
            $this->assertSame(FailureKind::INVALID_RESPONSE, $failure->getKind());
        }
    }

    private function images(): ResponseInterface
    {
        return new Response(json_encode(['data' => [['b64_json' => base64_encode('first')], ['b64_json' => base64_encode('second')]],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 10, 'total_tokens' => 15]], JSON_THROW_ON_ERROR));
    }

    /** @param list<ResponseInterface> $responses */
    private function client(array $responses, array &$requests): ClientInterface
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(count($responses)))->method('sendRequest')->willReturnCallback(
            static function (RequestInterface $request) use (&$responses, &$requests): ResponseInterface {
                $requests[] = ['uri' => (string) $request->getUri(), 'key' => $request->getHeaderLine('x-goog-api-key'),
                    'authorization' => $request->getHeaderLine('Authorization'), 'body' => json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR)];
                return array_shift($responses);
            },
        );
        return $client;
    }
}
