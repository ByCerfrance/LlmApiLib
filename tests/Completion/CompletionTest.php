<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use PHPUnit\Framework\TestCase;

class CompletionTest extends TestCase
{
    public function testGetModel()
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
        );

        $this->assertEquals('foo', $completion->getModel());
    }

    public function testWithModel()
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
        );
        $completion2 = $completion->withModel('bar');

        $this->assertEquals('foo', $completion->getModel());
        $this->assertEquals('bar', $completion2->getModel());
    }

    public function testGetMaxTokens()
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
            maxTokens: 1000,
        );

        $this->assertEquals(1000, $completion->getMaxTokens());
    }

    public function testWithMaxTokens()
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
            maxTokens: 1000,
        );
        $completion2 = $completion->withMaxTokens(100);

        $this->assertEquals(1000, $completion->getMaxTokens());
        $this->assertEquals(100, $completion2->getMaxTokens());
    }

    public function testGetTemperature()
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
            temperature: 1,
        );

        $this->assertEquals(1, $completion->getTemperature());
    }

    public function testWithTemperature()
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
            temperature: 1,
        );
        $completion2 = $completion->withTemperature(.2);

        $this->assertEquals(1, $completion->getTemperature());
        $this->assertEquals(.2, $completion2->getTemperature());
    }

    public function testGetTopP()
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
            top_p: 1,
        );

        $this->assertEquals(1, $completion->getTopP());
    }

    public function testWithTopP()
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
            top_p: 1,
        );
        $completion2 = $completion->withTopP(.5);

        $this->assertEquals(1, $completion->getTopP());
        $this->assertEquals(.5, $completion2->getTopP());
    }

    public function testCount()
    {
        $completion = new Completion(
            messages: [
                new Message(content: 'foo', role: RoleEnum::SYSTEM),
                new Message(content: 'bar', role: RoleEnum::USER),
                new Message(content: 'baz', role: RoleEnum::USER),
            ],
            model: 'foo'
        );

        $this->assertCount(3, $completion);
    }

    public function testGetIterator()
    {
        $messages = [];
        $completion = new Completion(
            messages: [
                $messages[] = new Message(content: 'foo', role: RoleEnum::SYSTEM),
                $messages[] = new Message(content: 'bar', role: RoleEnum::USER),
                $messages[] = new Message(content: 'baz', role: RoleEnum::USER),
            ],
            model: 'foo'
        );

        $this->assertEquals(new ArrayIterator($messages), $completion->getIterator());
    }

    public function testJsonSerialize()
    {
        $messages = [];
        $completion = new Completion(
            messages: [
                $messages[] = new Message(content: 'foo', role: RoleEnum::SYSTEM),
                $messages[] = new Message(content: 'bar', role: RoleEnum::USER),
                $messages[] = new Message(content: 'baz', role: RoleEnum::USER),
            ],
            model: 'foo',
            maxTokens: 123,
            temperature: .2,
            top_p: .5,
        );

        $this->assertEquals(
            [
                'max_tokens' => 123,
                'messages' => $messages,
                'model' => 'foo',
                'stream' => false,
                'temperature' => .2,
                'top_p' => .5,
            ],
            $completion->jsonSerialize()
        );
    }

    public function testGetLastMessage()
    {
        $completion = new Completion(
            messages: [
                new Message(content: 'foo', role: RoleEnum::SYSTEM),
                new Message(content: 'bar', role: RoleEnum::USER),
                $expected = new Message(content: 'baz', role: RoleEnum::USER),
            ],
            model: 'foo'
        );

        $this->assertSame($expected, $completion->getLastMessage());
    }

    public function testWithNewMessage()
    {
        $completion = new Completion(
            messages: [
                new Message(content: 'foo', role: RoleEnum::SYSTEM),
                new Message(content: 'bar', role: RoleEnum::USER),
            ],
            model: 'foo'
        );
        $completion2 = $completion->withNewMessage($expected = new Message(content: 'baz', role: RoleEnum::USER));

        $this->assertNotSame($completion, $completion2);
        $this->assertCount(2, $completion);
        $this->assertCount(3, $completion2);
        $this->assertSame($expected, $completion2->getLastMessage());

        $completion3 = $completion->withNewMessage('qux');

        $this->assertEquals('qux', $completion3->getLastMessage()->getContent());
    }
}
