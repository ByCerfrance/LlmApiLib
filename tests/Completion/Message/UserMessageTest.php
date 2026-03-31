<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion\Message;

use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\Message\UserMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(TextContent::class)]
class UserMessageTest extends TestCase
{
    public function testRoleIsUser(): void
    {
        $message = new UserMessage('hello');

        $this->assertSame(RoleEnum::USER, $message->getRole());
    }

    public function testContentFromString(): void
    {
        $message = new UserMessage('hello');

        $this->assertInstanceOf(TextContent::class, $message->getContent());
        $this->assertSame('hello', (string)$message->getContent());
    }

    public function testContentFromContentInterface(): void
    {
        $content = new TextContent('world');
        $message = new UserMessage($content);

        $this->assertSame($content, $message->getContent());
    }

    public function testJsonSerialize(): void
    {
        $message = new UserMessage('hello');

        $json = $message->jsonSerialize();

        $this->assertSame(RoleEnum::USER, $json['role']);
        $this->assertInstanceOf(TextContent::class, $json['content']);
    }

    public function testIsInstanceOfMessage(): void
    {
        $message = new UserMessage('hello');

        $this->assertInstanceOf(Message::class, $message);
    }
}
