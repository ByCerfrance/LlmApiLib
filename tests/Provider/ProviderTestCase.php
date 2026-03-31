<?php

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\FinishReason;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonSchemaFormat;
use ByCerfrance\LlmApiLib\Completion\Tool\Tool;
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
            ['Coucou, réponds-moi juste exactement "The rabbit comes out of its burrow!" si tout va bien, rien de plus.'],
            temperature: 0,
        );

        {
            $completion = $this->provider->chat($completion);
            $this->assertStringContainsString(
                strtolower('The rabbit comes out of its burrow!'),
                strtolower($test = $completion->getLastMessage()->getContent()),
            );
            sleep($this->sleep);

            $usage = $this->provider->getUsage();

            $this->assertGreaterThanOrEqual(20, $usage->getPromptTokens());
            $this->assertGreaterThanOrEqual(1, $usage->getCompletionTokens());
            $this->assertGreaterThanOrEqual(20, $usage->getTotalTokens());

            $prevUsage = clone $usage;
        }

        {
            $completion = $this->provider->chat(
                $completion->withNewMessage('Re-réponds moi maintenant "The rabbit returns to its burrow.".')
            );
            $this->assertStringContainsString(
                strtolower('The rabbit returns to its burrow.'),
                strtolower($completion->getLastMessage()->getContent()),
            );
            sleep($this->sleep);

            $usage = $this->provider->getUsage();

            $this->assertGreaterThan($prevUsage->getPromptTokens(), $usage->getPromptTokens());
            $this->assertGreaterThan($prevUsage->getCompletionTokens(), $usage->getCompletionTokens());
            $this->assertGreaterThan($prevUsage->getTotalTokens(), $usage->getTotalTokens());

            $prevUsage = clone $usage;
        }

        {
            $completion = $this->provider->chat($completion->withNewMessage('Re-donnes moi ta première réponse.'));
            $this->assertStringContainsString(
                strtolower('The rabbit comes out of its burrow!'),
                strtolower($completion->getLastMessage()->getContent()),
            );
            sleep($this->sleep);

            $usage = $this->provider->getUsage();

            $this->assertGreaterThan($prevUsage->getPromptTokens(), $usage->getPromptTokens());
            $this->assertGreaterThan($prevUsage->getCompletionTokens(), $usage->getCompletionTokens());
            $this->assertGreaterThan($prevUsage->getTotalTokens(), $usage->getTotalTokens());
        }

        $this->assertEquals(
            round(
                $usage->getPromptTokens() / 1000000 * 10 +
                $usage->getCompletionTokens() / 1000000 * 20,
                4
            ),
            $this->provider->getCost(),
        );
    }

    public function testResponseFormat(): void
    {
        if (!$this->provider->supports(Capability::JSON_SCHEMA)) {
            $this->markTestSkipped('Provider does not support tools capability');
        }

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

    public function testFinishReason(): void
    {
        $completion = $this->provider->chat(
            new Completion(
                messages: ['Racontes moi une histoire en 50 mots.'],
                maxTokens: 20,
            )
        );

        $this->assertSame(FinishReason::LENGTH, $completion->getFinishReason());
    }

    public function testGetScoring(): void
    {
        $this->assertEquals(2.25, $this->provider->getScoring(SelectionStrategy::BALANCED));
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->provider->supports(Capability::TEXT, Capability::JSON_OUTPUT));
        $this->assertTrue($this->provider->supports(Capability::TEXT, Capability::JSON_SCHEMA));
        $this->assertFalse($this->provider->supports(Capability::AUDIO));
    }

    public function testTools(): void
    {
        if (!$this->provider->supports(Capability::TOOLS)) {
            $this->markTestSkipped('Provider does not support tools capability');
        }

        $callCount = 0;
        $calculatorTool = new Tool(
            name: 'calculator',
            description: 'Perform a mathematical calculation',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'operation' => [
                        'type' => 'string',
                        'enum' => ['add', 'subtract', 'multiply', 'divide'],
                        'description' => 'The operation to perform',
                    ],
                    'a' => [
                        'type' => 'number',
                        'description' => 'First operand',
                    ],
                    'b' => [
                        'type' => 'number',
                        'description' => 'Second operand',
                    ],
                ],
                'required' => ['operation', 'a', 'b'],
            ],
            callback: function (array $args) use (&$callCount): array {
                $callCount++;
                $result = match ($args['operation']) {
                    'add' => $args['a'] + $args['b'],
                    'subtract' => $args['a'] - $args['b'],
                    'multiply' => $args['a'] * $args['b'],
                    'divide' => $args['a'] / $args['b'],
                };

                return ['result' => $result];
            },
        );

        $completion = (new Completion(
            messages: ['Calcule 15 multiplié par 7. Utilise l\'outil calculator.'],
            temperature: 0,
        ))
            ->withTools($calculatorTool)
            ->withMaxToolIterations(3);

        $response = $this->provider->chat($completion);

        $this->assertGreaterThanOrEqual(1, $callCount, 'Tool should have been called at least once');
        $this->assertStringContainsString(
            '105',
            (string)$response->getLastMessage()->getContent(),
            'Response should contain the calculation result',
        );
    }
}
