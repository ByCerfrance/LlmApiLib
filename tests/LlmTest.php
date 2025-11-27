<?php

namespace ByCerfrance\LlmApiLib\Tests;

use ByCerfrance\LlmApiLib\Capability;
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\Content\InputAudioContent;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Llm;
use ByCerfrance\LlmApiLib\LlmInterface;
use PHPUnit\Framework\TestCase;

class LlmTest extends TestCase
{
    public function testGetProviders(): void
    {
        $firstLlm = $this->createMock(LlmInterface::class);
        $secondLlm = $this->createMock(LlmInterface::class);

        $firstLlm->method('getCapabilities')->willReturn([Capability::DOCUMENT]);
        $secondLlm->method('getCapabilities')->willReturn([Capability::AUDIO]);

        $llm = new Llm($firstLlm, $secondLlm);

        $this->assertEquals(
            [$firstLlm, $secondLlm],
            iterator_to_array($llm->getProviders()),
        );
        $this->assertEquals(
            [$secondLlm],
            iterator_to_array(
                $llm->getProviders(
                    new Completion(
                        messages: [
                            new Message(content: new InputAudioContent('', ''), role: RoleEnum::USER),
                        ]
                    )
                )
            ),
        );
    }

    public function testGetCapabilities(): void
    {
        $firstLlm = $this->createMock(LlmInterface::class);
        $secondLlm = $this->createMock(LlmInterface::class);

        $firstLlm->method('getCapabilities')->willReturn([Capability::DOCUMENT]);
        $secondLlm->method('getCapabilities')->willReturn([Capability::AUDIO]);

        $llm = new Llm($firstLlm, $secondLlm);

        $this->assertEquals(
            [Capability::DOCUMENT, Capability::AUDIO],
            iterator_to_array($llm->getCapabilities()),
        );
    }

    public function testSupports(): void
    {
        $firstLlm = $this->createMock(LlmInterface::class);
        $secondLlm = $this->createMock(LlmInterface::class);

        $firstLlm
            ->method('supports')
            ->willReturnCallback(fn(Capability ...$capability) => empty(
            array_udiff(
                $capability,
                [Capability::DOCUMENT, Capability::IMAGE],
                fn(Capability $a, Capability $b) => strcmp($a->name, $b->name),
            )
            ));
        $secondLlm
            ->method('supports')
            ->willReturnCallback(fn(Capability ...$capability) => empty(
            array_udiff(
                $capability,
                [Capability::AUDIO],
                fn(Capability $a, Capability $b) => strcmp($a->name, $b->name),
            )
            ));

        $llm = new Llm($firstLlm, $secondLlm);

        $this->assertTrue($llm->supports(Capability::DOCUMENT));
        $this->assertTrue($llm->supports(Capability::AUDIO));
        $this->assertTrue($llm->supports(Capability::DOCUMENT, Capability::IMAGE));
        $this->assertFalse($llm->supports(Capability::DOCUMENT, Capability::AUDIO));
        $this->assertFalse($llm->supports(Capability::VIDEO));
    }
}
