<?php

namespace ByCerfrance\LlmApiLib\Tests\Completions;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completions\Completions;
use ByCerfrance\LlmApiLib\Completions\Message\Message;
use ByCerfrance\LlmApiLib\Completions\Message\RoleEnum;
use PHPUnit\Framework\TestCase;

class CompletionsTest extends TestCase
{
    public function testCount()
    {
        $completions = new Completions(
            new Message(content: 'foo', role: RoleEnum::SYSTEM),
            new Message(content: 'bar', role: RoleEnum::USER),
            new Message(content: 'baz', role: RoleEnum::USER),
        );

        $this->assertCount(3, $completions);
    }

    public function testGetIterator()
    {
        $messages = [];
        $completions = new Completions(
            $messages[] = new Message(content: 'foo', role: RoleEnum::SYSTEM),
            $messages[] = new Message(content: 'bar', role: RoleEnum::USER),
            $messages[] = new Message(content: 'baz', role: RoleEnum::USER),
        );

        $this->assertEquals(new ArrayIterator($messages), $completions->getIterator());
    }

    public function testJsonSerialize()
    {
        $messages = [];
        $completions = new Completions(
            $messages[] = new Message(content: 'foo', role: RoleEnum::SYSTEM),
            $messages[] = new Message(content: 'bar', role: RoleEnum::USER),
            $messages[] = new Message(content: 'baz', role: RoleEnum::USER),
        );

        $this->assertEquals(
            $messages,
            $completions->jsonSerialize()
        );
    }

    public function testGetLastMessage()
    {
        $completions = new Completions(
            new Message(content: 'foo', role: RoleEnum::SYSTEM),
            new Message(content: 'bar', role: RoleEnum::USER),
            $expected = new Message(content: 'baz', role: RoleEnum::USER),
        );

        $this->assertSame($expected, $completions->getLastMessage());
    }

    public function testWithNewMessage()
    {
        $completions = new Completions(
            new Message(content: 'foo', role: RoleEnum::SYSTEM),
            new Message(content: 'bar', role: RoleEnum::USER),
        );
        $completions2 = $completions->withNewMessage($expected = new Message(content: 'baz', role: RoleEnum::USER));

        $this->assertNotSame($completions, $completions2);
        $this->assertCount(2, $completions);
        $this->assertCount(3, $completions2);
        $this->assertSame($expected, $completions2->getLastMessage());

        $completions3 = $completions->withNewMessage('qux');

        $this->assertEquals('qux', $completions3->getLastMessage()->getContent());

    }
}
