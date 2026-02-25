<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Message;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completion\Content\DocumentUrlContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\Choices;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Model\Capability;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Choices::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(DocumentUrlContent::class)]
#[UsesClass(Capability::class)]
#[UsesClass(TextContent::class)]
class ChoicesTest extends TestCase
{
    public function testCount(): void
    {
        $choices = new Choices(
            new Message(content: 'foo', role: RoleEnum::USER),
            new Message(content: 'bar', role: RoleEnum::SYSTEM),
        );

        $this->assertCount(2, $choices);
    }

    public function testGetIterator(): void
    {
        $messages = [];
        $choices = new Choices(
            $messages[] = new Message(content: 'foo', role: RoleEnum::USER),
            $messages[] = new Message(content: 'bar', role: RoleEnum::SYSTEM),
        );

        $this->assertEquals(new ArrayIterator($messages), $choices->getIterator());
    }

    public function testGetRole(): void
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

    public function testGetContent(): void
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

    public function testJsonSerialize(): void
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

    public function testSetPreferred(): void
    {
        $choices = new Choices(
            new Message(content: 'foo', role: RoleEnum::ASSISTANT),
            new Message(content: 'bar', role: RoleEnum::ASSISTANT),
        );

        $this->assertEquals(0, $choices->getPreferred());
        $choices->setPreferred(1);
        $this->assertEquals(1, $choices->getPreferred());
    }

    public function testSetPreferredOutOfBounds(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $choices = new Choices(
            new Message(content: 'foo', role: RoleEnum::ASSISTANT),
            new Message(content: 'bar', role: RoleEnum::ASSISTANT),
        );
        $choices->setPreferred(2);
    }

    public function testRequiredCapabilities(): void
    {
        $choices = new Choices(
            new Message(content: new DocumentUrlContent(url: 'https://bycerfrance.fr'), role: RoleEnum::ASSISTANT),
            new Message(content: 'bar', role: RoleEnum::ASSISTANT),
        );

        $this->assertEquals(
            [Capability::DOCUMENT, Capability::OCR, Capability::TEXT],
            $choices->requiredCapabilities(),
        );
    }
}
