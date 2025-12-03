<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Capability;
use PHPUnit\Framework\TestCase;

class TextContentTest extends TestCase
{
    public function testFromFile(): void
    {
        $content = TextContent::fromFile(__DIR__ . '/content.txt');

        $this->assertEquals(
            $expected = file_get_contents(__DIR__ . '/content.txt'),
            $content->getContent(),
        );

        $content = TextContent::fromFile(fopen(__DIR__ . '/content.txt', 'r'));

        $this->assertEquals(
            $expected,
            $content->getContent(),
        );
    }

    public function testFromFileWithPlaceholders(): void
    {
        $content = TextContent::fromFile(
            __DIR__ . '/content.txt',
            ['R1' => 'FOO'],
        );
        $this->assertEquals(
            $expected = str_replace('{R1}', 'FOO', file_get_contents(__DIR__ . '/content.txt')),
            $content->getContent(),
        );

        $content = TextContent::fromFile(
            fopen(__DIR__ . '/content.txt', 'r'),
            ['R1' => 'FOO'],
        );
        $this->assertEquals(
            $expected,
            $content->getContent(),
        );
    }

    public function testGetContent(): void
    {
        $content = new TextContent(content: 'foo');
        $this->assertEquals('foo', $content->getContent());
        $this->assertEquals('foo', (string)$content);

        $content = new TextContent(content: 1);
        $this->assertSame('1', $content->getContent());
    }

    public function testJsonSerialize(): void
    {
        $content = new TextContent(content: 'foo');

        $this->assertEquals('foo', $content->jsonSerialize());
        $this->assertEquals(
            [
                'text' => 'foo',
                'type' => 'text',
            ],
            $content->jsonSerialize(true)
        );

        $content = new TextContent(content: 1);

        $this->assertEquals('1', $content->jsonSerialize());
        $this->assertEquals(
            [
                'text' => '1',
                'type' => 'text',
            ],
            $content->jsonSerialize(true)
        );
    }

    public function testRequiredCapabilities(): void
    {
        $content = new TextContent(content: 'foo');

        $this->assertEquals(
            [Capability::TEXT],
            $content->requiredCapabilities(),
        );
    }
}
