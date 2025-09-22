<?php

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonSchemaFormat;
use ByCerfrance\LlmApiLib\LlmInterface;
use PHPUnit\Framework\TestCase;

abstract class ProviderTestCase extends TestCase
{
    protected LlmInterface $provider;
    protected int $sleep = 0;

    public function testChat()
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

            $this->assertGreaterThanOrEqual(27, $this->provider->getUsage()->getPromptTokens());
            $this->assertGreaterThanOrEqual(2, $this->provider->getUsage()->getCompletionTokens());
            $this->assertGreaterThanOrEqual(29, $this->provider->getUsage()->getTotalTokens());
        }

        {
            $completion = $this->provider->chat($completion->withNewMessage('Re-réponds moi maintenant "KO".'));
            $this->assertStringContainsString(
                strtolower('KO'),
                strtolower($completion->getLastMessage()->getContent()),
            );
            sleep($this->sleep);

            $this->assertGreaterThanOrEqual(71, $this->provider->getUsage()->getPromptTokens());
            $this->assertGreaterThanOrEqual(5, $this->provider->getUsage()->getCompletionTokens());
            $this->assertGreaterThanOrEqual(76, $this->provider->getUsage()->getTotalTokens());
        }

        {
            $completion = $this->provider->chat($completion->withNewMessage('Re-donnes moi ta première réponse.'));
            $this->assertStringContainsString(
                strtolower('OK'),
                strtolower($completion->getLastMessage()->getContent()),
            );
            sleep($this->sleep);

            $this->assertGreaterThanOrEqual(131, $this->provider->getUsage()->getPromptTokens());
            $this->assertGreaterThanOrEqual(7, $this->provider->getUsage()->getCompletionTokens());
            $this->assertGreaterThanOrEqual(138, $this->provider->getUsage()->getTotalTokens());
        }
    }

    public function testResponseFormat()
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
}
