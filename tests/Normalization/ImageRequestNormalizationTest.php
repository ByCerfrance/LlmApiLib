<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Normalization;

use ByCerfrance\LlmApiLib\Content\TextFormat;
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Image\ImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Image\ImageResponse;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Normalization\ImageNormalizerInterface;
use ByCerfrance\LlmApiLib\Normalization\MessageNormalizer;
use ByCerfrance\LlmApiLib\Normalization\NullPromptNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptTarget;
use ByCerfrance\LlmApiLib\Normalization\TextNormalizer;
use ByCerfrance\LlmApiLib\Normalization\TextNormalizerInterface;
use ByCerfrance\LlmApiLib\Provider\ImageProviderInterface;
use ByCerfrance\LlmApiLib\Provider\ImageResult;
use ByCerfrance\LlmApiLib\Provider\ProviderImageRequest;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PromptNormalizer::class)]
#[CoversClass(NullPromptNormalizer::class)]
#[CoversClass(ImageGenerator::class)]
#[UsesClass(ImageAsset::class)]
#[UsesClass(ImageRequest::class)]
#[UsesClass(ImageResponse::class)]
#[UsesClass(Model::class)]
#[UsesClass(MessageNormalizer::class)]
#[UsesClass(PromptTarget::class)]
#[UsesClass(TextNormalizer::class)]
#[UsesClass(TextFormat::class)]
#[UsesClass(ImageResult::class)]
#[UsesClass(ProviderImageRequest::class)]
#[UsesClass(TokenUsage::class)]
class ImageRequestNormalizationTest extends TestCase
{
    public function testImageGeneratorNormalizesOrderedReferencesAndPromptOnACopy(): void
    {
        $first = ImageAsset::fromBytes('first original', 'image/png');
        $second = ImageAsset::fromBytes('second original', 'image/png');
        $firstOutput = ImageAsset::fromBytes('first normalized', 'image/webp');
        $secondOutput = ImageAsset::fromBytes('second normalized', 'image/webp');
        $images = $this->createMock(ImageNormalizerInterface::class);
        $images->expects(self::exactly(2))->method('normalize')->willReturnMap([
            [$first, $firstOutput], [$second, $secondOutput],
        ]);
        $normalizer = new PromptNormalizer(
            targets: [PromptTarget::IMAGE_PROMPT, PromptTarget::IMAGE_REFERENCE],
            textNormalizer: new TextNormalizer(trimText: true, collapseSpaces: true),
            imageNormalizer: $images,
            textFormat: TextFormat::PLAIN_TEXT,
        );
        $request = new ImageRequest('  Draw   this farm  ', count: 2, options: ['quality' => 'high'], aspectRatio: '16:9', images: [$first, $second]);
        $generated = ImageAsset::fromBytes('generated original', 'image/png');
        $provider = $this->createMock(ImageProviderInterface::class);
        $provider->expects(self::once())->method('generate')->willReturnCallback(
            static function (ProviderImageRequest $sent) use ($request, $firstOutput, $secondOutput, $generated): ImageResult {
                self::assertNotSame($request, $sent->request);
                self::assertSame('Draw this farm', $sent->request->getPrompt());
                self::assertSame([$firstOutput, $secondOutput], $sent->request->getImages());
                self::assertSame(2, $sent->request->getCount());
                self::assertSame(['quality' => 'high'], $sent->request->getOptions());
                self::assertSame('16:9', $sent->request->getAspectRatio());
                self::assertSame('model', $sent->model->getName());
                return new ImageResult([$generated], new TokenUsage(), 'test', 'model');
            },
        );
        $response = (new ImageGenerator($provider, new Model('model'), promptNormalizer: $normalizer))->generate($request);
        self::assertSame([$generated], $response->getImages());
        self::assertSame('  Draw   this farm  ', $request->getPrompt());
        self::assertSame([$first, $second], $request->getImages());
    }

    public function testStringPromptsUseTheSamePipeline(): void
    {
        $provider = $this->createMock(ImageProviderInterface::class);
        $provider->expects(self::once())->method('generate')->willReturnCallback(static function (ProviderImageRequest $request): ImageResult {
            self::assertSame('{"subject":"a  farm"}', $request->request->getPrompt());
            self::assertSame([], $request->request->getImages());
            return new ImageResult([ImageAsset::fromBytes('generated', 'image/png')], new TokenUsage(), 'test', 'model');
        });
        $normalizer = new PromptNormalizer([PromptTarget::IMAGE_PROMPT], new TextNormalizer(minifyJson: true));
        (new ImageGenerator($provider, new Model('model'), $normalizer))->generate(' { "subject": "a  farm" } ');
    }

    public function testImageTargetsAreIndependentAndChatTargetsDoNotSelectImageRequests(): void
    {
        $asset = ImageAsset::fromBytes('original', 'image/png');
        $replacement = ImageAsset::fromBytes('normalized', 'image/png');
        $images = $this->createMock(ImageNormalizerInterface::class);
        $images->expects(self::once())->method('normalize')->with($asset)->willReturn($replacement);
        $request = new ImageRequest(' { "subject": "farm" } ', images: [$asset]);
        $text = new TextNormalizer(minifyJson: true);
        foreach ([[], [PromptTarget::USER, PromptTarget::SYSTEM, PromptTarget::ASSISTANT_HISTORY, PromptTarget::TOOL_RESULT]] as $targets) {
            self::assertSame($request, (new PromptNormalizer($targets, $text, $images))->normalize($request));
        }
        $promptOnly = (new PromptNormalizer([PromptTarget::IMAGE_PROMPT], $text, $images))->normalize($request);
        self::assertSame('{"subject":"farm"}', $promptOnly->getPrompt());
        self::assertSame([$asset], $promptOnly->getImages());
        $referencesOnly = (new PromptNormalizer([PromptTarget::IMAGE_REFERENCE], $text, $images))->normalize($request);
        self::assertSame($request->getPrompt(), $referencesOnly->getPrompt());
        self::assertSame([$replacement], $referencesOnly->getImages());
        self::assertSame($request, (new NullPromptNormalizer())->normalize($request));
    }

    public function testImagePromptUsesDeclaredFormatRatherThanChatResolver(): void
    {
        $request = new ImageRequest("  Keep   this prompt\n");
        $resolver = static function (): never { throw new RuntimeException('Chat resolver must not be called for image prompts.'); };
        $unknown = new PromptNormalizer([PromptTarget::IMAGE_PROMPT], new TextNormalizer(trimText: true), textFormatResolver: $resolver);
        self::assertSame($request, $unknown->normalize($request));
        $plain = new PromptNormalizer([PromptTarget::IMAGE_PROMPT], new TextNormalizer(trimText: true), textFormat: TextFormat::PLAIN_TEXT, textFormatResolver: $resolver);
        $normalized = $plain->normalize($request);
        self::assertSame('Keep   this prompt', $normalized->getPrompt());
        self::assertSame($normalized, $plain->normalize($normalized));
    }

    public function testEmptyNormalizedPromptIsRejectedBeforeProviderCall(): void
    {
        $text = $this->createStub(TextNormalizerInterface::class);
        $text->method('normalize')->willReturn('');
        $provider = $this->createMock(ImageProviderInterface::class);
        $provider->expects(self::never())->method('generate');
        $generator = new ImageGenerator($provider, new Model('model'), new PromptNormalizer([PromptTarget::IMAGE_PROMPT], $text));
        $this->expectException(InvalidArgumentException::class);
        $generator->generate('Draw a farm');
    }

    public function testImageProcessingFailurePreventsProviderCall(): void
    {
        $images = $this->createStub(ImageNormalizerInterface::class);
        $failure = new RuntimeException('Missing image extension.');
        $images->method('normalize')->willThrowException($failure);
        $provider = $this->createMock(ImageProviderInterface::class);
        $provider->expects(self::never())->method('generate');
        $generator = new ImageGenerator($provider, new Model('model'), new PromptNormalizer([PromptTarget::IMAGE_REFERENCE], imageNormalizer: $images));
        $this->expectExceptionObject($failure);
        $generator->generate(new ImageRequest('Draw', images: [ImageAsset::fromBytes('image', 'image/png')]));
    }
}
