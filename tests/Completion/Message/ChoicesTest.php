<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion\Message;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completion\Content\DocumentUrlContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\FinishReason;
use ByCerfrance\LlmApiLib\Completion\Message\Choice;
use ByCerfrance\LlmApiLib\Completion\Message\Choices;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Model\Capability;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Choices::class)]
#[UsesClass(Choice::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(DocumentUrlContent::class)]
#[UsesClass(Capability::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(FinishReason::class)]
class ChoicesTest extends TestCase
{
    public function testCount(): void
    {
        $choices = new Choices(
            new Choice(new Message(content: 'foo', role: RoleEnum::USER)),
            new Choice(new Message(content: 'bar', role: RoleEnum::SYSTEM)),
        );

        $this->assertCount(2, $choices);
    }

    public function testGetIterator(): void
    {
        $choiceItems = [];
        $choices = new Choices(
            $choiceItems[] = new Choice(new Message(content: 'foo', role: RoleEnum::USER)),
            $choiceItems[] = new Choice(new Message(content: 'bar', role: RoleEnum::SYSTEM)),
        );

        $this->assertEquals(new ArrayIterator($choiceItems), $choices->getIterator());
    }

    public function testGetRole(): void
    {
        $choices = new Choices(
            new Choice(new Message(content: 'foo', role: RoleEnum::USER)),
            new Choice(new Message(content: 'bar', role: RoleEnum::SYSTEM)),
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
            new Choice(new Message(content: 'foo', role: RoleEnum::USER)),
            new Choice(new Message(content: 'bar', role: RoleEnum::SYSTEM)),
        );

        $this->assertEquals('foo', $choices->getContent());

        $choices->setPreferred(1);
        $this->assertEquals('bar', $choices->getContent());

        $choices->setPreferred(0);
        $this->assertEquals('foo', $choices->getContent());
    }

    public function testJsonSerialize(): void
    {
        $msg1 = new Message(content: 'foo', role: RoleEnum::USER);
        $msg2 = new Message(content: 'bar', role: RoleEnum::SYSTEM);
        $choices = new Choices(
            new Choice($msg1),
            new Choice($msg2),
        );

        $this->assertEquals($msg1->jsonSerialize(), $choices->jsonSerialize());

        $choices->setPreferred(1);
        $this->assertEquals($msg2->jsonSerialize(), $choices->jsonSerialize());

        $choices->setPreferred(0);
        $this->assertEquals($msg1->jsonSerialize(), $choices->jsonSerialize());
    }

    public function testSetPreferred(): void
    {
        $choices = new Choices(
            new Choice(new Message(content: 'foo', role: RoleEnum::ASSISTANT)),
            new Choice(new Message(content: 'bar', role: RoleEnum::ASSISTANT)),
        );

        $this->assertEquals(0, $choices->getPreferred());
        $choices->setPreferred(1);
        $this->assertEquals(1, $choices->getPreferred());
    }

    public function testSetPreferredOutOfBounds(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $choices = new Choices(
            new Choice(new Message(content: 'foo', role: RoleEnum::ASSISTANT)),
            new Choice(new Message(content: 'bar', role: RoleEnum::ASSISTANT)),
        );
        $choices->setPreferred(2);
    }

    public function testGetPreferredChoice(): void
    {
        $choice1 = new Choice(
            new Message(content: 'foo', role: RoleEnum::ASSISTANT),
            finishReason: FinishReason::STOP,
        );
        $choice2 = new Choice(
            new Message(content: 'bar', role: RoleEnum::ASSISTANT),
            finishReason: FinishReason::LENGTH,
        );

        $choices = new Choices($choice1, $choice2);

        $this->assertSame($choice1, $choices->getPreferredChoice());
        $this->assertSame(FinishReason::STOP, $choices->getPreferredChoice()->finishReason);

        $choices->setPreferred(1);
        $this->assertSame($choice2, $choices->getPreferredChoice());
        $this->assertSame(FinishReason::LENGTH, $choices->getPreferredChoice()->finishReason);
    }

    public function testRequiredCapabilities(): void
    {
        $choices = new Choices(
            new Choice(
                new Message(content: new DocumentUrlContent(url: 'https://bycerfrance.fr'), role: RoleEnum::ASSISTANT)
            ),
            new Choice(new Message(content: 'bar', role: RoleEnum::ASSISTANT)),
        );

        $this->assertEquals(
            [Capability::DOCUMENT, Capability::OCR, Capability::TEXT],
            $choices->requiredCapabilities(),
        );
    }
}
