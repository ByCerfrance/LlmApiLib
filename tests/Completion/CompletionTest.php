<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Capability;
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\Content\DocumentUrlContent;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonObjectFormat;
use PHPUnit\Framework\TestCase;

class CompletionTest extends TestCase
{
    public function testGetModel(): void
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
        );

        $this->assertEquals('foo', $completion->getModel());
    }

    public function testWithModel(): void
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
        );
        $completion2 = $completion->withModel('bar');

        $this->assertEquals('foo', $completion->getModel());
        $this->assertEquals('bar', $completion2->getModel());
    }

    public function testGetMaxTokens(): void
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
            maxTokens: 1000,
        );

        $this->assertEquals(1000, $completion->getMaxTokens());
    }

    public function testWithMaxTokens(): void
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

    public function testGetTemperature(): void
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
            temperature: 1,
        );

        $this->assertEquals(1, $completion->getTemperature());
    }

    public function testWithTemperature(): void
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

    public function testGetTopP(): void
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
            top_p: 1,
        );

        $this->assertEquals(1, $completion->getTopP());
    }

    public function testWithTopP(): void
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

    public function testGetSeed(): void
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
            seed: 4,
        );

        $this->assertEquals(4, $completion->getSeed());
    }

    public function testWithSeed(): void
    {
        $completion = new Completion(
            messages: [],
            model: 'foo',
        );
        $completion2 = $completion->withSeed(5);

        $this->assertNull($completion->getSeed());
        $this->assertEquals(5, $completion2->getSeed());
    }

    public function testCount(): void
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

    public function testGetIterator(): void
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

    public function testJsonSerialize(): void
    {
        $completion = new Completion(
            messages: $messages = [
                new Message(content: 'foo', role: RoleEnum::SYSTEM),
                new Message(content: 'bar', role: RoleEnum::USER),
                new Message(content: 'baz', role: RoleEnum::USER),
            ],
        );

        $this->assertEquals(
            [
                'max_tokens' => 1000,
                'messages' => $messages,
                'stream' => false,
                'temperature' => 1,
                'top_p' => 1,
            ],
            $completion->jsonSerialize()
        );

        $completion = new Completion(
            messages: $messages,
            model: 'foo',
            maxTokens: 123,
            temperature: .2,
            top_p: .5,
            seed: 16,
        );

        $this->assertEquals(
            [
                'max_tokens' => 123,
                'messages' => $messages,
                'model' => 'foo',
                'stream' => false,
                'temperature' => .2,
                'top_p' => .5,
                'seed' => 16,
            ],
            $completion->jsonSerialize()
        );
    }

    public function testGetLastMessage(): void
    {
        $completion = new Completion(
            messages: [
                new Message(content: 'foo', role: RoleEnum::SYSTEM),
                new Message(content: 'bar', role: RoleEnum::USER),
                $expectedAssistant = new Message(content: 'baz', role: RoleEnum::ASSISTANT),
                $expectedUser = new Message(content: 'qux', role: RoleEnum::USER),
            ],
            model: 'foo'
        );

        $this->assertSame($expectedUser, $completion->getLastMessage());
        $this->assertSame($expectedUser, $completion->getLastMessage(RoleEnum::USER));
        $this->assertSame($expectedAssistant, $completion->getLastMessage(RoleEnum::ASSISTANT));
    }

    public function testWithNewMessage(): void
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

    public function testRequiredCapabilities(): void
    {
        $completion = new Completion(
            messages: [
                new Message(content: 'foo', role: RoleEnum::USER),
                new Message(content: new DocumentUrlContent(url: 'https://bycerfrance.fr'), role: RoleEnum::USER),
            ],
            responseFormat: new JsonObjectFormat(),
        );

        $this->assertEquals(
            [
                Capability::JSON_OUTPUT,
                Capability::TEXT,
                Capability::DOCUMENT,
                Capability::OCR,
            ],
            $completion->requiredCapabilities(),
        );
    }
}
