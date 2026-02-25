<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completion\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Completion\Content\DocumentUrlContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Capability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayContent::class)]
class ArrayContentTest extends TestCase
{
    public function testGetIterator(): void
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

    public function testJsonSerialize(): void
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

    public function testRequiredCapabilities(): void
    {
        $content = new ArrayContent(
            new DocumentUrlContent(url: 'https://bycerfrance.fr'),
            'foo'
        );

        $this->assertEquals(
            [Capability::DOCUMENT, Capability::OCR, Capability::TEXT],
            $content->requiredCapabilities(),
        );
    }
}
