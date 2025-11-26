<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Capability;
use ByCerfrance\LlmApiLib\Completion\Content\DocumentUrlContent;
use PHPUnit\Framework\TestCase;

class DocumentUrlContentTest extends TestCase
{
    public function testGetUrl()
    {
        $content = new DocumentUrlContent(url: 'https://bycerfrance.fr');
        $this->assertEquals('https://bycerfrance.fr', $content->getUrl());

        $content = new DocumentUrlContent(url: $expected = Uri::createFromString('https://bycerfrance.fr'));
        $this->assertSame($expected, $content->getUrl());
    }

    public function testGetName()
    {
        $content = new DocumentUrlContent(url: 'https://bycerfrance.fr', name: 'foo');
        $this->assertEquals('foo', $content->getName());

        $content = new DocumentUrlContent(url: 'https://bycerfrance.fr');
        $this->assertNull($content->getName());
    }

    public function testGetDetail()
    {
        $content = new DocumentUrlContent(url: 'https://bycerfrance.fr', detail: 'foo');
        $this->assertEquals('foo', $content->getDetail());

        $content = new DocumentUrlContent(url: 'https://bycerfrance.fr');
        $this->assertNull($content->getDetail());
    }

    public function testJsonSerialize()
    {
        $content = new DocumentUrlContent(url: 'https://bycerfrance.fr', name: 'bar', detail: 'foo');
        $this->assertEquals(
            [
                'document_url' => [
                    'url' => 'https://bycerfrance.fr',
                    'detail' => 'foo',
                ],
                'document_name' => 'bar',
                'type' => 'document_url',
            ],
            $content->jsonSerialize()
        );
    }

    public function testRequiredCapabilities()
    {
        $content = new DocumentUrlContent(url: 'https://bycerfrance.fr');

        $this->assertEquals(
            [Capability::DOCUMENT, Capability::OCR],
            $content->requiredCapabilities(),
        );
    }
}
