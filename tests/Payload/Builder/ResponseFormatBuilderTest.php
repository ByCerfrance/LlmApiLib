<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Payload\Builder;

use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonObjectFormat;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonSchemaFormat;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\TextFormat;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\Builder\ResponseFormatBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Tests\Payload\Support\PayloadReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseFormatBuilder::class)]
class ResponseFormatBuilderTest extends TestCase
{
    public function testSupportsResponseFormatsOnly(): void
    {
        $builder = new ResponseFormatBuilder();

        $this->assertTrue($builder->supports(new TextFormat(), new BuildContext()));
        $this->assertFalse($builder->supports(new \stdClass(), new BuildContext()));
    }

    public function testBuildTextFormat(): void
    {
        $format = new TextFormat();
        $payload = (new ResponseFormatBuilder())->build($format, new PayloadBuilder(), new BuildContext());

        $this->assertEquals(PayloadReference::responseFormat($format), $payload);
    }

    public function testBuildJsonObjectFormat(): void
    {
        $format = new JsonObjectFormat();
        $payload = (new ResponseFormatBuilder())->build($format, new PayloadBuilder(), new BuildContext());

        $this->assertEquals(PayloadReference::responseFormat($format), $payload);
    }

    public function testBuildJsonSchemaFormat(): void
    {
        $format = new JsonSchemaFormat('SchemaName', ['type' => 'object'], true);
        $payload = (new ResponseFormatBuilder())->build($format, new PayloadBuilder(), new BuildContext());

        $this->assertEquals(PayloadReference::responseFormat($format), $payload);
    }
}
