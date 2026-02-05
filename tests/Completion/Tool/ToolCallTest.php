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
    }
}
