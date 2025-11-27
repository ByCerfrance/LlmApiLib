<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\ResponseFormat;

use ByCerfrance\LlmApiLib\Capability;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonObjectFormat;
use PHPUnit\Framework\TestCase;

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
