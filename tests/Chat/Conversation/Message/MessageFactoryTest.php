<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Chat\Conversation\Message;

use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\MessageFactory;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\SystemMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Content\ContentFactory;
use ByCerfrance\LlmApiLib\Content\NullContent;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Tool\ToolCall;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageFactory::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(SystemMessage::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ContentFactory::class)]
#[UsesClass(NullContent::class)]
class MessageFactoryTest extends TestCase
{
    public function testCreateUserMessage(): void
    {
        $message = MessageFactory::create([
            'role' => 'user',
            'content' => 'Hello',
        ]);

        $this->assertInstanceOf(UserMessage::class, $message);
        $this->assertSame(RoleEnum::USER, $message->getRole());
        $this->assertSame('Hello', (string)$message->getContent());
    }

    public function testCreateSystemMessage(): void
    {
        $message = MessageFactory::create([
            'role' => 'system',
            'content' => 'You are helpful',
        ]);

        $this->assertInstanceOf(SystemMessage::class, $message);
        $this->assertSame(RoleEnum::SYSTEM, $message->getRole());
        $this->assertSame('You are helpful', (string)$message->getContent());
    }

    public function testCreateAssistantMessage(): void
    {
        $message = MessageFactory::create([
            'role' => 'assistant',
            'content' => 'I can help',
        ]);

        $this->assertInstanceOf(AssistantMessage::class, $message);
        $this->assertSame(RoleEnum::ASSISTANT, $message->getRole());
        $this->assertSame('I can help', (string)$message->getContent());
    }

    public function testCreateAssistantMessageWithNullContent(): void
    {
        $message = MessageFactory::create([
            'role' => 'assistant',
            'content' => null,
        ]);

        $this->assertInstanceOf(AssistantMessage::class, $message);
        $this->assertSame('', (string)$message->getContent());
    }

    public function testCreateAssistantMessageWithToolCalls(): void
    {
        $message = MessageFactory::create([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [
                [
                    'id' => 'call_1',
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_weather',
                        'arguments' => '{"city": "Paris"}',
                    ],
                ],
            ],
        ]);

        $this->assertInstanceOf(AssistantMessage::class, $message);
        $this->assertTrue($message->hasToolCalls());
        $this->assertCount(1, $message->getToolCalls());
        $this->assertSame('get_weather', $message->getToolCalls()[0]->name);
    }

    public function testCreateAssistantMessageWithContentAndToolCalls(): void
    {
        $message = MessageFactory::create([
            'role' => 'assistant',
            'content' => 'Let me search for that',
            'tool_calls' => [
                [
                    'id' => 'call_1',
                    'type' => 'function',
                    'function' => [
                        'name' => 'search',
                        'arguments' => '{"query": "test"}',
                    ],
                ],
            ],
        ]);

        $this->assertInstanceOf(AssistantMessage::class, $message);
        $this->assertTrue($message->hasToolCalls());
        $this->assertSame('Let me search for that', (string)$message->getContent());
    }

    public function testCreateWithMissingContentDefaultsToEmpty(): void
    {
        $message = MessageFactory::create([
            'role' => 'user',
        ]);

        $this->assertInstanceOf(UserMessage::class, $message);
        $this->assertSame('', (string)$message->getContent());
    }
}
