<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Completion\Content\DocumentUrlContent;
use ByCerfrance\LlmApiLib\Model\Capability;
use PHPUnit\Framework\TestCase;

class DocumentUrlContentTest extends TestCase
{
    public function testFromFile(): void
    {
        $content = DocumentUrlContent::fromFile(__DIR__ . '/image.png', 'file.png');

        $this->assertEquals(
            $expected = 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/image.png')),
            $content->getUrl(),
        );

        $content = DocumentUrlContent::fromFile(fopen(__DIR__ . '/image.png', 'r'), 'file.png');

        $this->assertEquals(
            $expected,
            $content->getUrl(),
        );
    }

    public function testGetUrl(): void
    {
        $content = new DocumentUrlContent(url: 'https://bycerfrance.fr');
        $this->assertEquals('https://bycerfrance.fr', $content->getUrl());

        $content = new DocumentUrlContent(url: $expected = Uri::createFromString('https://bycerfrance.fr'));
        $this->assertSame($expected, $content->getUrl());
    }

    public function testGetName(): void
    {
        $content = new DocumentUrlContent(url: 'https://bycerfrance.fr', name: 'foo');
        $this->assertEquals('foo', $content->getName());

        $content = new DocumentUrlContent(url: 'https://bycerfrance.fr');
        $this->assertNull($content->getName());
    }

    public function testGetDetail(): void
    {
        $content = new DocumentUrlContent(url: 'https://bycerfrance.fr', detail: 'foo');
        $this->assertEquals('foo', $content->getDetail());

        $content = new DocumentUrlContent(url: 'https://bycerfrance.fr');
        $this->assertNull($content->getDetail());
    }

    public function testJsonSerialize(): void
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

    public function testRequiredCapabilities(): void
    {
        $content = new DocumentUrlContent(url: 'https://bycerfrance.fr');

        $this->assertEquals(
            [Capability::DOCUMENT, Capability::OCR],
            $content->requiredCapabilities(),
        );
    }
}
