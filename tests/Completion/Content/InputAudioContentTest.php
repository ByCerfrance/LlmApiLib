<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use ByCerfrance\LlmApiLib\Completion\Content\InputAudioContent;
use ByCerfrance\LlmApiLib\Model\Capability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InputAudioContent::class)]
#[UsesClass(Capability::class)]
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
