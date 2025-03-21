<?php

namespace ByCerfrance\LlmApiLib\Tests\Completions\Message;

use ByCerfrance\LlmApiLib\Completions\Message\Message;
use ByCerfrance\LlmApiLib\Completions\Message\RoleEnum;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    public function testGetRole()
    {
        $message = new Message(content: 'foo', role: RoleEnum::SYSTEM);
        $this->assertEquals(
            RoleEnum::SYSTEM,
            $message->getRole()
        );

        $message = new Message(content: 'foo');
        $this->assertEquals(
            RoleEnum::USER,
            $message->getRole()
        );
    }

    public function testGetContent()
    {
        $message = new Message(content: 'foo');

        $this->assertEquals(
            'foo',
            $message->getContent()
        );
    }

    public function testJsonSerialize()
    {
        $message = new Message(content: 'foo');

        $this->assertEquals(
            [
                'content' => 'foo',
                'role' => RoleEnum::USER,
            ],
            $message->jsonSerialize()
        );
    }
}
