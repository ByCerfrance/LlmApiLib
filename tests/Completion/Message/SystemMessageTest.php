<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion\Message;

use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\Message\SystemMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(TextContent::class)]
class SystemMessageTest extends TestCase
{
    public function testRoleIsSystem(): void
    {
        $message = new SystemMessage('You are a helpful assistant');

        $this->assertSame(RoleEnum::SYSTEM, $message->getRole());
    }

    public function testContentFromString(): void
    {
        $message = new SystemMessage('instructions');

        $this->assertInstanceOf(TextContent::class, $message->getContent());
        $this->assertSame('instructions', (string)$message->getContent());
    }

    public function testContentFromContentInterface(): void
    {
        $content = new TextContent('system prompt');
        $message = new SystemMessage($content);

        $this->assertSame($content, $message->getContent());
    }

    public function testJsonSerialize(): void
    {
        $message = new SystemMessage('prompt');

        $json = $message->jsonSerialize();

        $this->assertSame(RoleEnum::SYSTEM, $json['role']);
        $this->assertInstanceOf(TextContent::class, $json['content']);
    }

    public function testIsInstanceOfMessage(): void
    {
        $message = new SystemMessage('prompt');

        $this->assertInstanceOf(Message::class, $message);
    }
}
