<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Capability;
use ByCerfrance\LlmApiLib\Completion\Content\ImageUrlContent;
use PHPUnit\Framework\TestCase;

class ImageUrlContentTest extends TestCase
{
    public function testFromGdImage(): void
    {
        $content = ImageUrlContent::fromGdImage(imagecreate(10, 10));

        $this->assertEquals(
            'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD//gA+Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBkZWZhdWx0IHF1YWxpdHkK/9sAQwAIBgYHBgUIBwcHCQkICgwUDQwLCwwZEhMPFB0aHx4dGhwcICQuJyAiLCMcHCg3KSwwMTQ0NB8nOT04MjwuMzQy/9sAQwEJCQkMCwwYDQ0YMiEcITIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy/8AAEQgACgAKAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A+f6KKKAP/9k=',
            $content->getUrl(),
        );
    }

    public function testFromGdImageWithResizement(): void
    {
        $content = ImageUrlContent::fromGdImage(imagecreate(100, 100), maxSize: 10, format: 'png');

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
