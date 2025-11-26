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
    public function testGetProviders()
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

    public function testGetCapabilities()
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
}
