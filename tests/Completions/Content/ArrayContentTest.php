<?php

namespace ByCerfrance\LlmApiLib\Tests\Completions\Content;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completions\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Completions\Content\TextContent;
use PHPUnit\Framework\TestCase;

class ArrayContentTest extends TestCase
{
    public function testGetIterator()
    {
        $content = new ArrayContent(
            $foo = new TextContent('foo'),
            $bar = new TextContent('bar'),
        );

        $this->assertEquals(new ArrayIterator([$foo, $bar]), $content->getIterator());
    }

    public function testJsonSerialize()
    {
        $content = new ArrayContent(
            $foo = new TextContent('foo'),
            $bar = new TextContent('bar'),
        );

        $this->assertEquals(
            [
                $foo->jsonSerialize(true),
                $bar->jsonSerialize(true),
            ],
            $content->jsonSerialize(),
        );
    }
}
