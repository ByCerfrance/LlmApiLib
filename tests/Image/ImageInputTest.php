<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Image;

use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ImageAsset::class)]
#[CoversClass(ImageRequest::class)]
class ImageInputTest extends TestCase
{
    public function testLocalFileAndBytesRetainMimeAndOrder(): void
    {
        $path = __DIR__ . '/../Content/image.png';
        $first = ImageAsset::fromLocalFile($path, 'image/png');
        $second = ImageAsset::fromBytes('second image', 'image/webp');
        $request = new ImageRequest('Use both references', images: [$first, $second]);
        $this->assertSame(realpath($path), $first->localPath);
        $this->assertNull($first->base64);
        $this->assertSame(file_get_contents($path), $first->getBytes());
        $this->assertSame('image/png', $first->mimeType);
        $this->assertSame([$first, $second], $request->getImages());
        $this->assertSame('second image', $second->getBytes());
        $this->assertSame([], (new ImageRequest('Text only', 1, [], '1:1'))->getImages());
    }

    /** @return iterable<string, array{array}> */
    public static function invalidInputs(): iterable
    {
        yield 'remote URL' => [[new ImageAsset('image/png', url: 'https://example.test/image.png')]];
        yield 'untyped entry' => [['image.png']];
        yield 'map instead of list' => [['reference' => ImageAsset::fromBytes('image', 'image/png')]];
        yield 'empty MIME subtype' => [[new ImageAsset('image/', base64_encode('image'))]];
        yield 'MIME parameters' => [[new ImageAsset('image/png; charset=utf-8', base64_encode('image'))]];
        yield 'empty decoded bytes' => [[new ImageAsset('image/png', ' ')]];
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidInputsAreRejected(array $images): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ImageRequest('Draw', images: $images);
    }

    /** @return iterable<string, array{string}> */
    public static function nonLocalPaths(): iterable
    {
        yield 'remote' => ['https://example.test/image.png'];
        yield 'data wrapper' => ['data://text/plain,image'];
        yield 'file wrapper' => ['file://' . __DIR__ . '/../Content/image.png'];
        yield 'directory' => [__DIR__];
        yield 'missing file' => [__DIR__ . '/missing-image.png'];
    }

    #[DataProvider('nonLocalPaths')]
    public function testOnlyLocalRegularFilesAreRead(string $path): void
    {
        $this->expectException(RuntimeException::class);
        ImageAsset::fromLocalFile($path, 'image/png');
    }

    public function testEmptyImageBytesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ImageAsset::fromBytes('', 'image/png');
    }

    public function testLocalFileIsReadLazilyAndNotCached(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'image-input-');
        try {
            $image = ImageAsset::fromLocalFile($path, 'image/png');
            new ImageRequest('Edit', images: [$image]);
            file_put_contents($path, 'first contents');
            $this->assertSame(base64_encode('first contents'), $image->getBase64());
            file_put_contents($path, 'changed contents');
            $this->assertSame('changed contents', $image->getBytes());
            $this->assertNull($image->base64);
        } finally {
            unlink($path);
        }
    }

    public function testDeletedLocalFileFailsWhenRead(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'image-input-');
        $image = ImageAsset::fromLocalFile($path, 'image/png');
        unlink($path);
        new ImageRequest('Edit', images: [$image]);
        $this->expectException(RuntimeException::class);
        $image->getBase64();
    }

    public function testEmptyLocalFileFailsWhenRead(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'image-input-');
        try {
            $image = ImageAsset::fromLocalFile($path, 'image/png');
            $this->expectException(RuntimeException::class);
            $image->getBytes();
        } finally {
            unlink($path);
        }
    }
}
