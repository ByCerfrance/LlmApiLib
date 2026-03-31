<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Usage\Usage;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\Builder\MistralCompletionBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
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
