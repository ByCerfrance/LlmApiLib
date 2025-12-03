<?php

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonSchemaFormat;
use ByCerfrance\LlmApiLib\LlmInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use PHPUnit\Framework\TestCase;

abstract class ProviderTestCase extends TestCase
{
    protected LlmInterface $provider;
    protected int $sleep = 0;

    public function testChatAndUsageAndCost(): void
    {
        $completion = new Completion(
            ['Coucou, réponds-moi juste "OK" si tout va bien, rien de plus.'],
            temperature: 0,
        );

        {
            $completion = $this->provider->chat($completion);
            $this->assertStringContainsString(
                strtolower('OK'),
                strtolower($completion->getLastMessage()->getContent()),
            );
            sleep($this->sleep);

            $this->assertGreaterThanOrEqual(20, $this->provider->getUsage()->getPromptTokens());
            $this->assertGreaterThanOrEqual(1, $this->provider->getUsage()->getCompletionTokens());
            $this->assertGreaterThanOrEqual(20, $this->provider->getUsage()->getTotalTokens());

            $usage = clone $this->provider->getUsage();
        }

        {
            $completion = $this->provider->chat($completion->withNewMessage('Re-réponds moi maintenant "KO".'));
            $this->assertStringContainsString(
                strtolower('KO'),
                strtolower($completion->getLastMessage()->getContent()),
            );
            sleep($this->sleep);

            $this->assertGreaterThan($usage->getPromptTokens(), $this->provider->getUsage()->getPromptTokens());
            $this->assertGreaterThan($usage->getCompletionTokens(), $this->provider->getUsage()->getCompletionTokens());
            $this->assertGreaterThan($usage->getTotalTokens(), $this->provider->getUsage()->getTotalTokens());

            $usage = clone $this->provider->getUsage();
        }

        {
            $completion = $this->provider->chat($completion->withNewMessage('Re-donnes moi ta première réponse.'));
            $this->assertStringContainsString(
                strtolower('OK'),
                strtolower($completion->getLastMessage()->getContent()),
            );
            sleep($this->sleep);

            $this->assertGreaterThan($usage->getPromptTokens(), $this->provider->getUsage()->getPromptTokens());
            $this->assertGreaterThan($usage->getCompletionTokens(), $this->provider->getUsage()->getCompletionTokens());
            $this->assertGreaterThan($usage->getTotalTokens(), $this->provider->getUsage()->getTotalTokens());
        }

        $this->assertEquals(
            round(
                $this->provider->getUsage()->getPromptTokens() / 1000000 * 10 +
                $this->provider->getUsage()->getCompletionTokens() / 1000000 * 20,
                4
            ),
            $this->provider->getCost(),
        );
    }

    public function testResponseFormat(): void
    {
        $completion = $this->provider->chat(
            new Completion(
                messages: ['Donnes moi la réponse de l\'addition de 1+1.'],
                responseFormat: new JsonSchemaFormat(
                    name: $schemaName = 'MathReasoning',
                    schema: [
                        'properties' => [
                            'result' => [
                                'title' => 'Math Reasoning Result',
                                'type' => 'number'
                            ]
                        ],
                        'additionalProperties' => false,
                        'required' => [
                            'result'
                        ],
                        'title' => $schemaName,
                        'type' => 'object'
                    ],
                    strict: true,
                ),
                temperature: 0
            )
        );
        $this->assertJsonStringEqualsJsonString(
            strtolower('{"result":2}'),
            strtolower($completion->getLastMessage()->getContent()),
        );
    }

    public function testGetScoring(): void
    {
        $this->assertEquals(2.25, $this->provider->getScoring(SelectionStrategy::BALANCED));
    }

    public function testGetCapabilities(): void
    {
        $this->assertEquals(
            Capability::defaults(),
            $this->provider->getCapabilities(),
        );
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->provider->supports(Capability::TEXT, Capability::JSON_OUTPUT));
        $this->assertFalse($this->provider->supports(Capability::TEXT, Capability::JSON_SCHEMA));
        $this->assertFalse($this->provider->supports(Capability::AUDIO));
    }
}
