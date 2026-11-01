<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Normalization;

use ByCerfrance\LlmApiLib\Content\ImageContent;
use ByCerfrance\LlmApiLib\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Normalization\AbstractImageNormalizer;
use ByCerfrance\LlmApiLib\Normalization\GdImageNormalizer;
use ByCerfrance\LlmApiLib\Normalization\ImageNormalizer;
use ByCerfrance\LlmApiLib\Normalization\ImageNormalizerInterface;
use ByCerfrance\LlmApiLib\Normalization\ImageOrientation;
use ByCerfrance\LlmApiLib\Normalization\ImagickImageNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(AbstractImageNormalizer::class)]
#[CoversClass(GdImageNormalizer::class)]
#[CoversClass(ImagickImageNormalizer::class)]
#[CoversClass(ImageNormalizer::class)]
#[CoversClass(ImageOrientation::class)]
#[UsesClass(ImageContent::class)]
#[UsesClass(ImageUrlContent::class)]
#[UsesClass(ImageAsset::class)]
class ImageNormalizerTest extends TestCase
{
    /** @return iterable<string, array{class-string<ImageNormalizerInterface>}> */
    public static function backends(): iterable
    {
        yield 'gd' => [GdImageNormalizer::class];
        yield 'imagick' => [ImagickImageNormalizer::class];
        yield 'automatic' => [ImageNormalizer::class];
    }

    private function requireBackend(string $class): void
    {
        $available = match ($class) {
            GdImageNormalizer::class => extension_loaded('gd'),
            ImagickImageNormalizer::class => extension_loaded('imagick'),
            default => extension_loaded('gd') || extension_loaded('imagick'),
        };
        if (!$available) {
            self::markTestSkipped('Optional image backend is not installed.');
        }
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private static function png(): string
    {
        $pixels = str_repeat("\0" . str_repeat("\xff\0\0\x80", 40), 20);
        return "\x89PNG\r\n\x1a\n"
            . self::chunk('IHDR', pack('NNCCCCC', 40, 20, 8, 6, 0, 0, 0))
            . self::chunk('IDAT', gzcompress($pixels)) . self::chunk('IEND', '');
    }

    #[DataProvider('backends')]
    public function testResizePreservesRatioOriginalAndContentRepresentation(string $class): void
    {
        $this->requireBackend($class);
        $bytes = self::png();
        $input = new ImageUrlContent('data:image/png;base64,' . base64_encode($bytes), 'low');
        $normalizer = new $class(maxWidth: 15, maxHeight: 5);
        $output = $normalizer->normalize($input);
        self::assertInstanceOf(ImageUrlContent::class, $output);
        self::assertSame('low', $output->getDetail());
        $result = base64_decode(explode(',', $output->getUrl(), 2)[1], true);
        $size = getimagesizefromstring($result);
        self::assertSame([10, 5, 'image/png'], [$size[0], $size[1], $size['mime']]);
        self::assertSame('data:image/png;base64,' . base64_encode($bytes), $input->getUrl());
        self::assertSame($output, $normalizer->normalize($input));
        self::assertSame($output, $normalizer->normalize($output));
        if (extension_loaded('gd')) {
            $decoded = imagecreatefromstring($result);
            self::assertGreaterThan(0, imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0))['alpha']);
        }
    }

    #[DataProvider('backends')]
    public function testConversionProducesDecodableJpegAndWebpWithoutEnlargement(string $class): void
    {
        $this->requireBackend($class);
        $input = new ImageContent(ImageAsset::fromBytes(self::png(), 'image/png'));
        foreach (['jpeg', 'webp'] as $format) {
            $normalizer = new $class(maxWidth: 100, maxHeight: 100, quality: 70, format: $format);
            $output = $normalizer->normalize($input);
            self::assertInstanceOf(ImageContent::class, $output);
            self::assertSame('image/' . $format, $output->image->mimeType);
            $size = getimagesizefromstring($output->image->getBytes());
            self::assertSame([40, 20, 'image/' . $format], [$size[0], $size[1], $size['mime']]);
            self::assertSame($output, $normalizer->normalize($output));
        }
        self::assertSame(self::png(), $input->image->getBytes());
        self::assertSame($input, (new $class(maxWidth: 100))->normalize($input));
    }

    #[DataProvider('backends')]
    public function testRemoteImagesAreNotFetchedAndLocalFilesRemainUnchanged(string $class): void
    {
        $this->requireBackend($class);
        $normalizer = new $class(maxWidth: 10);
        foreach ([new ImageUrlContent('https://example.invalid/image.png'), new ImageContent(new ImageAsset(null, url: 'https://example.invalid/image.png'))] as $remote) {
            self::assertSame($remote, $normalizer->normalize($remote));
        }
        $path = tempnam(sys_get_temp_dir(), 'llm-normalization-');
        try {
            file_put_contents($path, self::png());
            $input = new ImageContent(ImageAsset::fromLocalFile($path, 'image/png'));
            $output = $normalizer->normalize($input);
            self::assertSame([10, 5], array_slice(getimagesizefromstring($output->image->getBytes()), 0, 2));
            self::assertNull($output->image->localPath);
            self::assertSame(self::png(), file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    #[DataProvider('backends')]
    public function testAnimatedPngIsRejectedBeforeFramesCanBeLost(string $class): void
    {
        $this->requireBackend($class);
        $bytes = substr(self::png(), 0, 33) . self::chunk('acTL', pack('NN', 2, 0)) . substr(self::png(), 33);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Animated images');
        (new $class(maxWidth: 10))->normalize(new ImageContent(ImageAsset::fromBytes($bytes, 'image/png')));
    }

    #[DataProvider('backends')]
    public function testInvalidImageDataFailsExplicitly(string $class): void
    {
        $this->requireBackend($class);
        $this->expectException(RuntimeException::class);
        (new $class(maxWidth: 10))->normalize(new ImageContent(ImageAsset::fromBytes('invalid pixels', 'image/png')));
    }

    #[DataProvider('backends')]
    public function testExifOrientationIsAppliedBeforeResizing(string $class): void
    {
        $this->requireBackend($class);
        $jpeg = (new $class(format: 'jpeg'))->normalize(new ImageContent(ImageAsset::fromBytes(self::png(), 'image/png')))->image->getBytes();
        $tiff = "II\x2a\0" . pack('V', 8) . pack('v', 1) . pack('vvVvvV', 0x0112, 3, 1, 6, 0, 0);
        $exif = "Exif\0\0" . $tiff;
        $oriented = substr($jpeg, 0, 2) . "\xff\xe1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2);
        $output = (new $class(maxHeight: 10, format: 'png'))->normalize(new ImageContent(ImageAsset::fromBytes($oriented, 'image/jpeg')));
        self::assertSame([5, 10], array_slice(getimagesizefromstring($output->image->getBytes()), 0, 2));
        self::assertSame(1, ImageOrientation::read($output->image->getBytes(), 'png'));
    }

    public function testAutomaticSelectionPrefersImagickThenGd(): void
    {
        $this->requireBackend(ImageNormalizer::class);
        $image = new ImageContent(ImageAsset::fromBytes(self::png(), 'image/png'));
        $expected = extension_loaded('imagick') ? new ImagickImageNormalizer(maxWidth: 10) : new GdImageNormalizer(maxWidth: 10);
        $actual = (new ImageNormalizer(maxWidth: 10))->normalize($image);
        self::assertSame($expected->normalize($image)->image->getBytes(), $actual->image->getBytes());
    }

    #[DataProvider('backends')]
    public function testAssetsCanBeNormalizedDirectlyAndReusedAcrossChatContents(string $class): void
    {
        $this->requireBackend($class);
        $input = ImageAsset::fromBytes(self::png(), 'image/png');
        $normalizer = new $class(maxWidth: 10);
        $output = $normalizer->normalize($input);
        self::assertInstanceOf(ImageAsset::class, $output);
        self::assertSame([10, 5], array_slice(getimagesizefromstring($output->getBytes()), 0, 2));
        self::assertSame($output, $normalizer->normalize($input));
        self::assertSame($output, $normalizer->normalize($output));
        self::assertSame($output, $normalizer->normalize(new ImageContent($input))->image);
        self::assertSame(self::png(), $input->getBytes());
        $remote = new ImageAsset(null, url: 'https://example.invalid/image.png');
        self::assertSame($remote, $normalizer->normalize($remote));
        $small = ImageAsset::fromBytes(self::png(), 'image/png');
        self::assertSame($small, (new $class(maxWidth: 100))->normalize($small));
    }

    #[DataProvider('backends')]
    public function testCustomImageSubtypeRetainsItsRepresentation(string $class): void
    {
        $this->requireBackend($class);
        $image = new readonly class(ImageAsset::fromBytes(self::png(), 'image/png')) extends ImageContent {};
        self::assertSame($image, (new $class(maxWidth: 1))->normalize($image));
    }

    #[DataProvider('backends')]
    public function testEveryExifOrientationPreservesVisualCorners(string $class): void
    {
        $this->requireBackend($class);
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD is used to inspect output pixels.');
        }
        $pixels = '';
        for ($y = 0; $y < 20; $y++) {
            $pixels .= "\0";
            for ($x = 0; $x < 40; $x++) {
                $pixels .= $y < 10 ? ($x < 20 ? "\xff\0\0\xff" : "\0\xff\0\xff") : ($x < 20 ? "\0\0\xff\xff" : "\xff\xff\0\xff");
            }
        }
        $expectedCorners = [1 => 0xff0000, 2 => 0x00ff00, 3 => 0xffff00, 4 => 0x0000ff, 5 => 0xff0000, 6 => 0x0000ff, 7 => 0xffff00, 8 => 0x00ff00];
        foreach ($expectedCorners as $orientation => $expectedCorner) {
            $exif = "MM\0\x2a" . pack('N', 8) . pack('n', 1) . pack('nnNnnN', 0x0112, 3, 1, $orientation, 0, 0);
            $bytes = "\x89PNG\r\n\x1a\n" . self::chunk('IHDR', pack('NNCCCCC', 40, 20, 8, 6, 0, 0, 0))
                . self::chunk('eXIf', $exif) . self::chunk('IDAT', gzcompress($pixels)) . self::chunk('IEND', '');
            $output = (new $class(maxWidth: 10, maxHeight: 10))->normalize(new ImageContent(ImageAsset::fromBytes($bytes, 'image/png')));
            $decoded = imagecreatefromstring($output->image->getBytes());
            $color = imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0));
            foreach (['red' => 16, 'green' => 8, 'blue' => 0] as $channel => $shift) {
                self::assertEqualsWithDelta(($expectedCorner >> $shift) & 0xff, $color[$channel], 15, sprintf('Orientation %d, channel %s', $orientation, $channel));
            }
            self::assertSame($orientation >= 5 ? [5, 10] : [10, 5], [imagesx($decoded), imagesy($decoded)]);
        }
    }

    #[DataProvider('backends')]
    public function testAnimatedWebpIsRejected(string $class): void
    {
        $this->requireBackend($class);
        $webp = (new $class(format: 'webp'))->normalize(new ImageContent(ImageAsset::fromBytes(self::png(), 'image/png')))->image->getBytes();
        $body = substr($webp, 8) . 'ANIM' . pack('V', 6) . str_repeat("\0", 6);
        $animated = 'RIFF' . pack('V', strlen($body)) . $body;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Animated images');
        (new $class(maxWidth: 10))->normalize(new ImageContent(ImageAsset::fromBytes($animated, 'image/webp')));
    }

    #[RequiresFunction('proc_open')]
    public function testMissingExtensionsFailOnlyOnUse(): void
    {
        $script = <<<'PHP'
require $argv[1];
$image = new ByCerfrance\LlmApiLib\Content\ImageUrlContent('https://example.invalid/image.png');
$messages = [];
foreach (['GdImageNormalizer', 'ImagickImageNormalizer', 'ImageNormalizer'] as $name) {
    $class = 'ByCerfrance\\LlmApiLib\\Normalization\\' . $name;
    $normalizer = new $class(maxWidth: 10);
    try {
        $normalizer->normalize($image);
        throw new LogicException('Expected a missing-extension exception.');
    } catch (RuntimeException $exception) {
        $messages[] = $exception->getMessage();
    }
}
echo json_encode($messages, JSON_THROW_ON_ERROR);
PHP;
        $process = proc_open([PHP_BINARY, '-n', '-r', $script, dirname(__DIR__, 2) . '/vendor/autoload.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors . $output);
        $messages = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(3, $messages);
        self::assertStringContainsString('gd extension', $messages[0]);
        self::assertStringContainsString('imagick extension', $messages[1]);
        self::assertStringContainsString('imagick or gd', $messages[2]);
    }

    /** @return iterable<string, array{array<string, int|string>}> */
    public static function invalidOptions(): iterable
    {
        yield 'width' => [['maxWidth' => 0]];
        yield 'height' => [['maxHeight' => -1]];
        yield 'quality below zero' => [['quality' => -1]];
        yield 'quality above 100' => [['quality' => 101]];
        yield 'format' => [['format' => 'gif']];
    }

    #[DataProvider('invalidOptions')]
    public function testInvalidOptionsAreRejectedWithoutLoadingExtensions(array $options): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ImageNormalizer(...$options);
    }
}
