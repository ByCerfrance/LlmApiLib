<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion\Tool;

use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolCall::class)]
class ToolCallTest extends TestCase
{
    public function testConstruction(): void
    {
        $toolCall = new ToolCall(
            id: 'call_123',
            name: 'get_weather',
            arguments: ['location' => 'Paris'],
        );

        $this->assertSame('call_123', $toolCall->id);
        $this->assertSame('get_weather', $toolCall->name);
        $this->assertSame(['location' => 'Paris'], $toolCall->arguments);
        $this->assertNull($toolCall->additionalFields);
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => 'call_456',
            'type' => 'function',
            'function' => [
                'name' => 'search',
                'arguments' => '{"query":"PHP tools"}',
            ],
        ];

        $toolCall = ToolCall::fromArray($data);

        $this->assertSame('call_456', $toolCall->id);
        $this->assertSame('search', $toolCall->name);
        $this->assertSame(['query' => 'PHP tools'], $toolCall->arguments);
        $this->assertNull($toolCall->additionalFields);
    }

    public function testFromArrayWithEmptyArguments(): void
    {
        $data = [
            'id' => 'call_789',
            'type' => 'function',
            'function' => [
                'name' => 'no_args',
                'arguments' => '',
            ],
        ];

        $toolCall = ToolCall::fromArray($data);

        $this->assertSame([], $toolCall->arguments);
    }

    public function testJsonSerialize(): void
    {
        $toolCall = new ToolCall(
            id: 'call_abc',
            name: 'calculate',
            arguments: ['a' => 1, 'b' => 2],
        );

        $json = $toolCall->jsonSerialize();

        $this->assertSame('call_abc', $json['id']);
        $this->assertSame('function', $json['type']);
        $this->assertSame('calculate', $json['function']['name']);
        $this->assertSame('{"a":1,"b":2}', $json['function']['arguments']);
        $this->assertArrayNotHasKey('extra_content', $json);
    }

    public function testFromArrayWithAdditionalFields(): void
    {
        $data = [
            'id' => 'call_google',
            'type' => 'function',
            'function' => [
                'name' => 'check_flight',
                'arguments' => '{"flight":"AA100"}',
            ],
            'extra_content' => [
                'google' => [
                    'thought_signature' => 'sig-abc',
                ],
            ],
        ];

        $toolCall = ToolCall::fromArray($data);

        $this->assertSame('call_google', $toolCall->id);
        $this->assertSame('check_flight', $toolCall->name);
        $this->assertSame(['flight' => 'AA100'], $toolCall->arguments);
        $this->assertSame(
            ['extra_content' => ['google' => ['thought_signature' => 'sig-abc']]],
            $toolCall->additionalFields,
        );
    }

    public function testJsonSerializeWithAdditionalFields(): void
    {
        $toolCall = new ToolCall(
            id: 'call_google',
            name: 'check_flight',
            arguments: ['flight' => 'AA100'],
            additionalFields: [
                'extra_content' => [
                    'google' => [
                        'thought_signature' => 'sig-abc',
                    ],
                ],
            ],
        );

        $json = $toolCall->jsonSerialize();

        $this->assertSame('sig-abc', $json['extra_content']['google']['thought_signature']);
        $this->assertSame('call_google', $json['id']);
        $this->assertSame('function', $json['type']);
        $this->assertSame('check_flight', $json['function']['name']);
    }

    public function testFromArrayRoundTripPreservesAdditionalFields(): void
    {
        $data = [
            'id' => 'call_1',
            'type' => 'function',
            'function' => [
                'name' => 'calculator',
                'arguments' => '{"a":1,"b":2}',
            ],
            'extra_content' => [
                'google' => [
                    'thought_signature' => 'sig-xyz',
                ],
            ],
        ];

        $toolCall = ToolCall::fromArray($data);
        $serialized = $toolCall->jsonSerialize();

        $this->assertSame($data['extra_content'], $serialized['extra_content']);
        $this->assertSame($data['id'], $serialized['id']);
        $this->assertSame($data['function']['name'], $serialized['function']['name']);
        $this->assertSame($data['function']['arguments'], $serialized['function']['arguments']);
    }

    public function testFromArrayWithMultipleAdditionalFields(): void
    {
        $data = [
            'id' => 'call_multi',
            'type' => 'function',
            'function' => [
                'name' => 'search',
                'arguments' => '{}',
            ],
            'extra_content' => ['google' => ['thought_signature' => 'sig']],
            'metadata' => ['trace_id' => 'trace-123'],
        ];

        $toolCall = ToolCall::fromArray($data);

        $this->assertSame(
            [
                'extra_content' => ['google' => ['thought_signature' => 'sig']],
                'metadata' => ['trace_id' => 'trace-123'],
            ],
            $toolCall->additionalFields,
        );

        $json = $toolCall->jsonSerialize();

        $this->assertSame('sig', $json['extra_content']['google']['thought_signature']);
        $this->assertSame('trace-123', $json['metadata']['trace_id']);
    }
}
