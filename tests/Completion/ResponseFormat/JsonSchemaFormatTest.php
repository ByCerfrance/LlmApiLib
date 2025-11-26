<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\ResponseFormat;

use ByCerfrance\LlmApiLib\Capability;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonSchemaFormat;
use PHPUnit\Framework\TestCase;
use stdClass;

class JsonSchemaFormatTest extends TestCase
{
    public function testJsonSerialize()
    {
        $format = new JsonSchemaFormat(name: 'foo', schema: [], strict: true);

        $this->assertEquals(
            [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'foo',
                    'schema' => new stdClass(),
                    'strict' => true,
                ],
            ],
            $format->jsonSerialize(),
        );
    }

    public function testRequiredCapabilities()
    {
        $format = new JsonSchemaFormat(name: 'foo', schema: [], strict: true);

        $this->assertEquals(
            [Capability::JSON_SCHEMA],
            $format->requiredCapabilities(),
        );
    }
}
