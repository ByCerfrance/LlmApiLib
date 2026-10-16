<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Image;

use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Image\ImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Image\ImageResponse;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\ImageProviderInterface;
use ByCerfrance\LlmApiLib\Provider\ImageResult;
use ByCerfrance\LlmApiLib\Provider\ProviderImageRequest;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageGenerator::class)]
#[CoversClass(ImageRequest::class)]
#[UsesClass(ImageAsset::class)]
#[UsesClass(ImageResponse::class)]
#[UsesClass(Model::class)]
#[UsesClass(ImageResult::class)]
#[UsesClass(ProviderImageRequest::class)]
#[UsesClass(TokenUsage::class)]
class ImageGeneratorTest extends TestCase
{
    public function testStringPromptBecomesARequestForTheConfiguredModel(): void
    {
        $received = null;
        $provider = $this->createMock(ImageProviderInterface::class);
        $provider->expects($this->once())->method('generate')->willReturnCallback(
            static function (ProviderImageRequest $request) use (&$received): ImageResult {
                $received = $request;

                return new ImageResult([new ImageAsset(null, url: 'https://example.test/image.png')], new TokenUsage(), 'test', 'model');
            },
        );
        $response = (new ImageGenerator($provider, new Model('model')))->generate('Draw a farm');
        $this->assertSame('model', $received->model->getName());
        $this->assertSame('Draw a farm', $received->request->getPrompt());
        $this->assertSame(1, $received->request->getCount());
        $this->assertNull($received->request->getAspectRatio());
        $this->assertSame('https://example.test/image.png', $response->getImages()[0]->url);
    }

    public function testModelWithoutImageOutputIsRejected(): void
    {
        $provider = $this->createMock(ImageProviderInterface::class);
        $provider->expects($this->never())->method('generate');
        $this->expectException(InvalidArgumentException::class);
        (new ImageGenerator($provider, new Model('text', capabilities: [Capability::TEXT])))->generate('Draw');
    }

    public function testAspectRatioIsKept(): void
    {
        $this->assertSame('16:9', (new ImageRequest('Draw', aspectRatio: '16:9'))->getAspectRatio());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidRequests(): iterable
    {
        yield 'empty prompt' => [['prompt' => ' ']];
        yield 'count' => [['prompt' => 'Draw', 'count' => 0]];
        yield 'reserved option' => [['prompt' => 'Draw', 'options' => ['model' => 'other']]];
        yield 'aspect ratio' => [['prompt' => 'Draw', 'aspectRatio' => 'wide']];
        yield 'zero ratio' => [['prompt' => 'Draw', 'aspectRatio' => '0:1']];
    }

    /** @param array<string, mixed> $arguments */
    #[DataProvider('invalidRequests')]
    public function testInvalidRequestsAreRejected(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ImageRequest(...$arguments);
    }
}
