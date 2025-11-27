<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Capability;
use ByCerfrance\LlmApiLib\Completion\Content\ImageUrlContent;
use PHPUnit\Framework\TestCase;

class ImageUrlContentTest extends TestCase
{
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
