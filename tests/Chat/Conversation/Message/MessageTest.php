<?php

namespace ByCerfrance\LlmApiLib\Tests\Chat\Conversation\Message;

use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Content\DocumentUrlContent;
use ByCerfrance\LlmApiLib\Content\TextContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(DocumentUrlContent::class)]
#[UsesClass(TextContent::class)]
class MessageTest extends TestCase
{
    public function testGetRole(): void
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

    public function testGetContent(): void
    {
        $message = new Message(content: 'foo');

        $this->assertEquals(
            'foo',
            $message->getContent()
        );
    }

    public function testJsonSerialize(): void
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

    public function testRequiredCapabilities(): void
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
