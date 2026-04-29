<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion\ResponseFormat;

use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonSchemaFormat;
use ByCerfrance\LlmApiLib\Model\Capability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(JsonSchemaFormat::class)]
#[UsesClass(Capability::class)]
class JsonSchemaFormatTest extends TestCase
{
    public function testJsonSerialize(): void
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

    public function testJsonSerializeStripsSchemaKeyword(): void
    {
        $format = new JsonSchemaFormat(
            name: 'bar',
            schema: [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ],
            strict: true,
        );

        $result = $format->jsonSerialize();

        $schema = (array)$result['json_schema']['schema'];
        $this->assertArrayNotHasKey('$schema', $schema);
        $this->assertSame('object', $schema['type']);
    }

    public function testRequiredCapabilities(): void
    {
        $format = new JsonSchemaFormat(name: 'foo', schema: [], strict: true);

        $this->assertEquals(
            [Capability::JSON_SCHEMA],
            $format->requiredCapabilities(),
        );
    }
}
