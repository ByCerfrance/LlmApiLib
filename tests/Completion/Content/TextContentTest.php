<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
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
