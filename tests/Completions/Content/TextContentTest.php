<?php

namespace ByCerfrance\LlmApiLib\Tests\Completions\Content;

use ByCerfrance\LlmApiLib\Completions\Content\TextContent;
use PHPUnit\Framework\TestCase;

class TextContentTest extends TestCase
{
    public function testGetContent()
    {
        $content = new TextContent(content: 'foo');
        $this->assertEquals('foo', $content->getContent());
        $this->assertEquals('foo', (string)$content);
    }

    public function testJsonSerialize()
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
    }
}
