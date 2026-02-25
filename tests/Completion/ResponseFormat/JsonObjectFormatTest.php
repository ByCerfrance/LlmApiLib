<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\ResponseFormat;

use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonObjectFormat;
use ByCerfrance\LlmApiLib\Model\Capability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonObjectFormat::class)]
#[UsesClass(Capability::class)]
class JsonObjectFormatTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $format = new JsonObjectFormat();

        $this->assertEquals(
            ['type' => 'json_object'],
            $format->jsonSerialize()
        );
    }

    public function testRequiredCapabilities(): void
    {
        $format = new JsonObjectFormat();

        $this->assertEquals(
            [Capability::JSON_OUTPUT],
            $format->requiredCapabilities(),
        );
    }
}
