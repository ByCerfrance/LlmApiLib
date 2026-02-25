<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use ByCerfrance\LlmApiLib\Completion\Content\ContentFactory;
use ByCerfrance\LlmApiLib\Completion\Content\DocumentUrlContent;
use ByCerfrance\LlmApiLib\Completion\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Completion\Content\InputAudioContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(ContentFactory::class)]
class ContentFactoryTest extends TestCase
{
    public function testCreateWithString()
    {
        $content = ContentFactory::create('foo');

        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertEquals('foo', $content->getContent());
    }

    public function testCreateWithArray()
    {
        $content = ContentFactory::create(['type' => 'text', 'text' => 'foo']);

        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertEquals('foo', $content->getContent());
    }

    public function testCreateWithInvalidContentType()
    {
        $this->expectException(InvalidArgumentException::class);

        ContentFactory::create(new stdClass());
    }

    public function testCreateFromArray_DocumentUrl()
    {
        $content = ContentFactory::createFromArray(['type' => 'document_url', 'document_url' => 'foo']);

        $this->assertInstanceOf(DocumentUrlContent::class, $content);
        $this->assertEquals('foo', $content->getUrl());
    }

    public function testCreateFromArray_ImageUrl()
    {
        $content = ContentFactory::createFromArray(['type' => 'image_url', 'image_url' => 'foo']);

        $this->assertInstanceOf(ImageUrlContent::class, $content);
        $this->assertEquals('foo', $content->getUrl());
    }

    public function testCreateFromArray_InputAudio()
    {
        $content = ContentFactory::createFromArray([
            'type' => 'input_audio',
            'input_audio' => ['data' => 'foo', 'format' => 'bar',],
        ]);

        $this->assertInstanceOf(InputAudioContent::class, $content);
        $this->assertEquals('foo', $content->getData());
        $this->assertEquals('bar', $content->getFormat());
    }

    public function testCreateFromArray_Text()
    {
        $content = ContentFactory::createFromArray(['type' => 'text', 'text' => 'foo']);

        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertEquals('foo', (string)$content);
    }

    public function testCreateFromArrayWithUnknownType()
    {
        $this->expectException(InvalidArgumentException::class);

        ContentFactory::createFromArray(['type' => 'foo']);
    }

    public function testCreateFromString()
    {
        $content = ContentFactory::createFromString('foo');

        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertEquals('foo', $content->getContent());
    }
}
