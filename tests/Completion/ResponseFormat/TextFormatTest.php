<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\ResponseFormat;

use ByCerfrance\LlmApiLib\Completion\ResponseFormat\TextFormat;
use ByCerfrance\LlmApiLib\Model\Capability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextFormat::class)]
class TextFormatTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $format = new TextFormat();

        $this->assertEquals(
            ['type' => 'text'],
            $format->jsonSerialize()
        );
    }

    public function testRequiredCapabilities(): void
    {
        $format = new TextFormat();

        $this->assertEquals(
            [Capability::TEXT],
            $format->requiredCapabilities(),
        );
    }
}
