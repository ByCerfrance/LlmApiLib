<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Completion\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Model\Capability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageUrlContent::class)]
class ImageUrlContentTest extends TestCase
{
    public function testFromGdImage(): void
    {
        $content = ImageUrlContent::fromGdImage(imagecreatetruecolor(10, 10), format: 'png');

        $this->assertStringStartsWith(
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADUlEQVQYlWNgGAWkAwABNgABxYufBwAAAABJRU5ErkJggg==',
            $content->getUrl(),
        );
    }

    public function testFromGdImageWithResizement(): void
    {
        $content = ImageUrlContent::fromGdImage(imagecreatetruecolor(100, 100), maxSize: 10, format: 'png');

        $this->assertEquals(
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADUlEQVQYlWNgGAWkAwABNgABxYufBwAAAABJRU5ErkJggg==',
            $content->getUrl(),
        );
    }

    public function testFromFile(): void
    {
        $content = ImageUrlContent::fromFile(__DIR__ . '/image.png');

        $this->assertEquals(
            $expected = 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/image.png')),
            $content->getUrl(),
        );

        $content = ImageUrlContent::fromFile(fopen(__DIR__ . '/image.png', 'r'));

        $this->assertEquals(
            $expected,
            $content->getUrl(),
        );
    }

    public function testGetUrl(): void
    {
        $content = new ImageUrlContent(url: 'https://bycerfrance.fr');
        $this->assertEquals('https://bycerfrance.fr', $content->getUrl());

        $content = new ImageUrlContent(url: $expected = Uri::createFromString('https://bycerfrance.fr'));
        $this->assertSame($expected, $content->getUrl());
    }

    public function testGetDetail(): void
    {
        $content = new ImageUrlContent(url: 'https://bycerfrance.fr', detail: 'foo');
        $this->assertEquals('foo', $content->getDetail());

        $content = new ImageUrlContent(url: 'https://bycerfrance.fr');
        $this->assertNull($content->getDetail());
    }

    public function testJsonSerialize(): void
    {
        $content = new ImageUrlContent(url: 'https://bycerfrance.fr', detail: 'foo');
        $this->assertEquals(
            [
                'image_url' => [
                    'url' => 'https://bycerfrance.fr',
                    'detail' => 'foo',
                ],
                'type' => 'image_url',
            ],
            $content->jsonSerialize()
        );
    }

    public function testRequiredCapabilities(): void
    {
        $content = new ImageUrlContent(url: 'https://bycerfrance.fr');

        $this->assertEquals(
            [Capability::IMAGE, Capability::OCR],
            $content->requiredCapabilities(),
        );
    }
}
