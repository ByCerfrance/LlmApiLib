<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\ReasoningEffort;
use ByCerfrance\LlmApiLib\Completion\ServiceTier;
use ByCerfrance\LlmApiLib\Completion\ToolChoice;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\Builder\MistralCompletionBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Usage\Usage;
use ByCerfrance\LlmApiLib\Provider\AbstractProvider;
use ByCerfrance\LlmApiLib\Provider\Generic;
use ByCerfrance\LlmApiLib\Provider\Google;
use ByCerfrance\LlmApiLib\Provider\Mistral;
use ByCerfrance\LlmApiLib\Provider\OpenAi;
use ByCerfrance\LlmApiLib\Provider\Ovh;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

#[CoversClass(AbstractProvider::class)]
#[CoversClass(OpenAi::class)]
#[CoversClass(Mistral::class)]
#[CoversClass(Google::class)]
#[CoversClass(Ovh::class)]
#[CoversClass(Generic::class)]
#[UsesClass(PayloadBuilder::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(MistralCompletionBuilder::class)]
#[UsesClass(ReasoningEffort::class)]
#[UsesClass(ServiceTier::class)]
#[UsesClass(ToolChoice::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(ModelInfo::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Usage::class)]
#[UsesClass(Capability::class)]
class PayloadMappingTest extends TestCase
{
    public function testOpenAiUsesMaxCompletionTokensInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends OpenAi {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                maxTokens: 123,
            ),
        );

        $this->assertArrayHasKey('max_completion_tokens', $payload);
        $this->assertArrayNotHasKey('max_tokens', $payload);
        $this->assertSame(123, $payload['max_completion_tokens']);
    }

    public function testMistralKeepsLegacyMaxTokensInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Mistral {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                maxTokens: 123,
            ),
        );

        $this->assertArrayHasKey('max_tokens', $payload);
        $this->assertArrayNotHasKey('max_completion_tokens', $payload);
        $this->assertSame(123, $payload['max_tokens']);
    }

    public function testGoogleRoundTripPreservesExtraContent(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Google {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(messages: [
                new Message('Use calculator tool'),
                new AssistantMessage(toolCalls: [
                    new ToolCall(
                        'call_1',
                        'calculator',
                        ['a' => 1, 'b' => 2],
                        additionalFields: [
                            'extra_content' => [
                                'google' => ['thought_signature' => 'sig-xyz'],
                            ],
                        ],
                    ),
                ]),
            ]),
        );

        $toolCallPayload = $payload['messages'][1]['tool_calls'][0] ?? [];

        $this->assertSame('sig-xyz', $toolCallPayload['extra_content']['google']['thought_signature'] ?? null);
        $this->assertSame('call_1', $toolCallPayload['id']);
        $this->assertSame('calculator', $toolCallPayload['function']['name']);
    }

    public function testOvhUsesModernMaxCompletionTokensInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Ovh {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                maxTokens: 200,
            ),
        );

        $this->assertArrayHasKey('max_completion_tokens', $payload);
        $this->assertArrayNotHasKey('max_tokens', $payload);
        $this->assertSame(200, $payload['max_completion_tokens']);
    }

    public function testGenericUsesModernMaxCompletionTokensInPayload(): void
    {
        $provider = new readonly class('https://example.com/v1/chat/completions', 'key', new ModelInfo(
            'model'
        ), $this->createClient()) extends Generic {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                maxTokens: 300,
            ),
        );

        $this->assertArrayHasKey('max_completion_tokens', $payload);
        $this->assertArrayNotHasKey('max_tokens', $payload);
        $this->assertSame(300, $payload['max_completion_tokens']);
    }

    public function testOpenAiPreservesServiceTierInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends OpenAi {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                serviceTier: ServiceTier::FLEX,
            ),
        );

        $this->assertArrayHasKey('service_tier', $payload);
        $this->assertSame(ServiceTier::FLEX, $payload['service_tier']);
    }

    public function testMistralStripsServiceTierFromPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Mistral {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                serviceTier: ServiceTier::AUTO,
            ),
        );

        $this->assertArrayNotHasKey('service_tier', $payload);
    }

    public function testGooglePreservesServiceTierInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Google {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                serviceTier: ServiceTier::FLEX,
            ),
        );

        $this->assertArrayHasKey('service_tier', $payload);
        $this->assertSame(ServiceTier::FLEX, $payload['service_tier']);
    }

    public function testOpenAiPreservesReasoningEffortInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends OpenAi {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                reasoningEffort: ReasoningEffort::XHIGH,
            ),
        );

        $this->assertArrayHasKey('reasoning_effort', $payload);
        $this->assertSame(ReasoningEffort::XHIGH, $payload['reasoning_effort']);
    }

    public function testMistralFallbacksReasoningEffortInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Mistral {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        // MEDIUM -> LOW -> NONE (fallback chain, Mistral supports HIGH and NONE)
        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                reasoningEffort: ReasoningEffort::MEDIUM,
            ),
        );

        $this->assertArrayHasKey('reasoning_effort', $payload);
        $this->assertSame(ReasoningEffort::NONE, $payload['reasoning_effort']);
    }

    public function testMistralKeepsHighReasoningEffort(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Mistral {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                reasoningEffort: ReasoningEffort::HIGH,
            ),
        );

        $this->assertArrayHasKey('reasoning_effort', $payload);
        $this->assertSame(ReasoningEffort::HIGH, $payload['reasoning_effort']);
    }

    public function testMistralFallbacksXhighToHighReasoningEffort(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Mistral {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                reasoningEffort: ReasoningEffort::XHIGH,
            ),
        );

        $this->assertArrayHasKey('reasoning_effort', $payload);
        $this->assertSame(ReasoningEffort::HIGH, $payload['reasoning_effort']);
    }

    public function testOpenAiPreservesParallelToolCallsInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends OpenAi {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                parallelToolCalls: false,
            ),
        );

        $this->assertArrayHasKey('parallel_tool_calls', $payload);
        $this->assertFalse($payload['parallel_tool_calls']);
    }

    public function testMistralPreservesParallelToolCallsInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Mistral {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                parallelToolCalls: false,
            ),
        );

        $this->assertArrayHasKey('parallel_tool_calls', $payload);
        $this->assertFalse($payload['parallel_tool_calls']);
    }

    public function testGooglePreservesParallelToolCallsInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Google {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                parallelToolCalls: true,
            ),
        );

        $this->assertArrayHasKey('parallel_tool_calls', $payload);
        $this->assertTrue($payload['parallel_tool_calls']);
    }

    public function testParallelToolCallsAbsentWhenNull(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends OpenAi {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
            ),
        );

        $this->assertArrayNotHasKey('parallel_tool_calls', $payload);
    }

    public function testOpenAiPreservesToolChoiceInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends OpenAi {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                toolChoice: ToolChoice::REQUIRED,
            ),
        );

        $this->assertArrayHasKey('tool_choice', $payload);
        $this->assertSame(ToolChoice::REQUIRED, $payload['tool_choice']);
    }

    public function testMistralRemapsToolChoiceRequiredToAny(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Mistral {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                toolChoice: ToolChoice::REQUIRED,
            ),
        );

        $this->assertArrayHasKey('tool_choice', $payload);
        $this->assertSame('any', $payload['tool_choice']);
    }

    public function testMistralPreservesToolChoiceAutoInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Mistral {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                toolChoice: ToolChoice::AUTO,
            ),
        );

        $this->assertArrayHasKey('tool_choice', $payload);
        $this->assertSame(ToolChoice::AUTO, $payload['tool_choice']);
    }

    public function testGooglePreservesToolChoiceInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Google {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                toolChoice: ToolChoice::REQUIRED,
            ),
        );

        $this->assertArrayHasKey('tool_choice', $payload);
        $this->assertSame(ToolChoice::REQUIRED, $payload['tool_choice']);
    }

    public function testToolChoiceAbsentWhenNull(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends OpenAi {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
            ),
        );

        $this->assertArrayNotHasKey('tool_choice', $payload);
    }

    public function testGooglePreservesReasoningEffortInPayload(): void
    {
        $provider = new readonly class('key', new ModelInfo('model'), $this->createClient()) extends Google {
            public function exposeCreateBody(Completion $completion): array
            {
                return $this->createBody($completion);
            }
        };

        $payload = $provider->exposeCreateBody(
            new Completion(
                messages: [new Message('hello')],
                reasoningEffort: ReasoningEffort::HIGH,
            ),
        );

        $this->assertArrayHasKey('reasoning_effort', $payload);
        $this->assertSame(ReasoningEffort::HIGH, $payload['reasoning_effort']);
    }

    private function createClient(): ClientInterface
    {
        return new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('Client should not be called in payload tests');
            }
        };
    }
}
