<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completion\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use PHPUnit\Framework\TestCase;

class ArrayContentTest extends TestCase
{
    public function testGetIterator()
    {
        $content = new ArrayContent(
            $foo = new TextContent('foo'),
            $qux = 'qux',
            [
                $baz = new TextContent('baz'),
                $quxx = 'quxx',
            ],
            null,
            $bar = new TextContent('bar'),
        );

        $this->assertEquals(
            new ArrayIterator([$foo, new TextContent($qux), $baz, $quxx, $bar]),
            $content->getIterator()
        );
        $this->assertSame($foo, $content->getIterator()[0]);
    }

    public function testJsonSerialize()
    {
        $content = new ArrayContent(
            $foo = new TextContent('foo'),
            $qux = 'qux',
            [
                $baz = new TextContent('baz'),
                $quxx = 'quxx',
            ],
            null,
            $bar = new TextContent('bar'),
        );

        $this->assertEquals(
            [
                $foo->jsonSerialize(true),
                [
                    'type' => 'text',
                    'text' => $qux,
                ],
                $baz->jsonSerialize(true),
                [
                    'type' => 'text',
                    'text' => $quxx,
                ],
                $bar->jsonSerialize(true),
            ],
            $content->jsonSerialize(),
        );
    }
}
