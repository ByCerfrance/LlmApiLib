<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Message;

use ByCerfrance\LlmApiLib\Completion\Content\DocumentUrlContent;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
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

    public function testRequiredCapabilities()
    {
        $message = new Message(
            content: $content = new DocumentUrlContent(url: 'https://bycerfrance.fr'),
            role: RoleEnum::ASSISTANT,
        );

        $this->assertSame(
            $content->requiredCapabilities(),
            $message->requiredCapabilities(),
        );
    }
}
