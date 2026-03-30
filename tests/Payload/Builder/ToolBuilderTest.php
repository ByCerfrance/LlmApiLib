<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Payload\Builder;

use ByCerfrance\LlmApiLib\Completion\Tool\Tool;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\Builder\ToolBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Tests\Payload\Support\PayloadReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolBuilder::class)]
class ToolBuilderTest extends TestCase
{
    public function testSupportsToolTypesOnly(): void
    {
        $builder = new ToolBuilder();
        $tool = new Tool('sum', 'sum two numbers', ['type' => 'object'], fn(array $args) => $args);
        $toolCall = new ToolCall('call-1', 'sum', ['a' => 1, 'b' => 2]);
        $toolCollection = new ToolCollection($tool);

        $this->assertTrue($builder->supports($tool, new BuildContext()));
        $this->assertTrue($builder->supports($toolCall, new BuildContext()));
        $this->assertTrue($builder->supports($toolCollection, new BuildContext()));
        $this->assertFalse($builder->supports(new \stdClass(), new BuildContext()));
    }

    public function testBuildTool(): void
    {
        $tool = new Tool('sum', 'sum two numbers', ['type' => 'object'], fn(array $args) => $args);

        $payload = (new ToolBuilder())->build($tool, new PayloadBuilder(), new BuildContext());

        $this->assertEquals(PayloadReference::tool($tool), $payload);
    }

    public function testBuildToolCallWithoutAdditionalFields(): void
    {
        $toolCall = new ToolCall('call-1', 'sum', ['a' => 1, 'b' => 2]);

        $payload = (new ToolBuilder())->build($toolCall, new PayloadBuilder(), new BuildContext());

        $this->assertEquals(PayloadReference::toolCall($toolCall), $payload);
        $this->assertArrayNotHasKey('extra_content', $payload);
    }

    public function testBuildToolCallWithAdditionalFields(): void
    {
        $toolCall = new ToolCall(
            'call-1',
            'check_flight',
            ['flight' => 'AA100'],
            additionalFields: [
                'extra_content' => [
                    'google' => ['thought_signature' => 'sig-abc'],
                ],
            ],
        );

        $payload = (new ToolBuilder())->build($toolCall, new PayloadBuilder(), new BuildContext());

        $this->assertEquals(PayloadReference::toolCall($toolCall), $payload);
        $this->assertSame('sig-abc', $payload['extra_content']['google']['thought_signature']);
    }

    public function testBuildToolCollection(): void
    {
        $toolA = new Tool('sum', 'sum two numbers', ['type' => 'object'], fn(array $args) => $args);
        $toolB = new Tool('mul', 'multiply two numbers', ['type' => 'object'], fn(array $args) => $args);
        $toolCollection = new ToolCollection($toolA, $toolB);

        $payload = (new ToolBuilder())->build($toolCollection, new PayloadBuilder(), new BuildContext());

        $this->assertEquals(PayloadReference::toolCollection($toolCollection), $payload);
    }
}
