<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use ByCerfrance\LlmApiLib\Capability;
use ByCerfrance\LlmApiLib\Completion\Content\InputAudioContent;
use PHPUnit\Framework\TestCase;

class InputAudioContentTest extends TestCase
{
    public function testGetData(): void
    {
        $content = new InputAudioContent(data: 'foo', format: 'bar');
        $this->assertEquals('foo', $content->getData());
    }

    public function testGetFormat(): void
    {
        $content = new InputAudioContent(data: 'foo', format: 'bar');
        $this->assertEquals('bar', $content->getFormat());
    }

    public function testJsonSerialize(): void
    {
        $content = new InputAudioContent(data: 'foo', format: 'bar');

        $this->assertEquals(
            [
                'input_audio' => [
                    'data' => 'foo',
                    'format' => 'bar',
                ],
                'type' => 'input_audio',
            ],
            $content->jsonSerialize(true)
        );
    }

    public function testRequiredCapabilities(): void
    {
        $content = new InputAudioContent(data: 'foo', format: 'bar');

        $this->assertEquals(
            [Capability::AUDIO],
            $content->requiredCapabilities(),
        );
    }
}
