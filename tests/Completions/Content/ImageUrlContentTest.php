<?php

namespace ByCerfrance\LlmApiLib\Tests\Completions\Content;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Completions\Content\ImageUrlContent;
use PHPUnit\Framework\TestCase;

class ImageUrlContentTest extends TestCase
{
    public function testGetUrl()
    {
        $content = new ImageUrlContent(url: 'https://bycerfrance.fr');
        $this->assertEquals('https://bycerfrance.fr', $content->getUrl());

        $content = new ImageUrlContent(url: $expected = Uri::createFromString('https://bycerfrance.fr'));
        $this->assertSame($expected, $content->getUrl());
    }

    public function testGetDetail()
    {
        $content = new ImageUrlContent(url: 'https://bycerfrance.fr', detail: 'foo');
        $this->assertEquals('foo', $content->getDetail());

        $content = new ImageUrlContent(url: 'https://bycerfrance.fr');
        $this->assertNull($content->getDetail());
    }

    public function testJsonSerialize()
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
}
