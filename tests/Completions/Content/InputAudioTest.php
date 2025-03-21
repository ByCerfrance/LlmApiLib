<?php

namespace ByCerfrance\LlmApiLib\Tests\Completions\Content;

use ByCerfrance\LlmApiLib\Completions\Content\InputAudio;
use PHPUnit\Framework\TestCase;

class InputAudioTest extends TestCase
{
    public function testGetData()
    {
        $content = new InputAudio(data: 'foo', format: 'bar');
        $this->assertEquals('foo', $content->getData());
    }

    public function testGetFormat()
    {
        $content = new InputAudio(data: 'foo', format: 'bar');
        $this->assertEquals('bar', $content->getFormat());
    }

    public function testJsonSerialize()
    {
        $content = new InputAudio(data: 'foo', format: 'bar');

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
}
