<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Message;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completion\Message\Choices;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use PHPUnit\Framework\TestCase;

class ChoicesTest extends TestCase
{
    public function testCount()
    {
        $choices = new Choices(
            new Message(content: 'foo', role: RoleEnum::USER),
            new Message(content: 'bar', role: RoleEnum::SYSTEM),
        );

        $this->assertCount(2, $choices);
    }

    public function testGetIterator()
    {
        $messages = [];
        $choices = new Choices(
            $messages[] = new Message(content: 'foo', role: RoleEnum::USER),
            $messages[] = new Message(content: 'bar', role: RoleEnum::SYSTEM),
        );

        $this->assertEquals(new ArrayIterator($messages), $choices->getIterator());
    }

    public function testGetRole()
    {
        $choices = new Choices(
            new Message(content: 'foo', role: RoleEnum::USER),
            new Message(content: 'bar', role: RoleEnum::SYSTEM),
        );

        $this->assertEquals(RoleEnum::USER, $choices->getRole());

        $choices->setPreferred(1);
        $this->assertEquals(RoleEnum::SYSTEM, $choices->getRole());

        $choices->setPreferred(0);
        $this->assertEquals(RoleEnum::USER, $choices->getRole());
    }

    public function testGetContent()
    {
        $choices = new Choices(
            new Message(content: 'foo', role: RoleEnum::USER),
            new Message(content: 'bar', role: RoleEnum::SYSTEM),
        );

        $this->assertEquals('foo', $choices->getContent());

        $choices->setPreferred(1);
        $this->assertEquals('bar', $choices->getContent());

        $choices->setPreferred(0);
        $this->assertEquals('foo', $choices->getContent());
    }

    public function testJsonSerialize()
    {
        $choices = new Choices(
            $expected1 = new Message(content: 'foo', role: RoleEnum::USER),
            $expected2 = new Message(content: 'bar', role: RoleEnum::SYSTEM),
        );

        $this->assertEquals(
            $expected1->jsonSerialize(),
            $choices->jsonSerialize()
        );

        $choices->setPreferred(1);
        $this->assertEquals(
            $expected2->jsonSerialize(),
            $choices->jsonSerialize()
        );

        $choices->setPreferred(0);
        $this->assertEquals(
            $expected1->jsonSerialize(),
            $choices->jsonSerialize()
        );
    }
}
