<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider\Mistral;

use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\SystemMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Chat\ReasoningEffort;
use ByCerfrance\LlmApiLib\Chat\ToolChoice;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\ModelChatBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\JsonTransport;
use ByCerfrance\LlmApiLib\Provider\Mistral\Mistral;
use ByCerfrance\LlmApiLib\Provider\Mistral\MistralChatBuilder;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\ChatContentBuilder;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Tests\Provider\OpenAiCompatible\OpenAiCompatibleTestCase;
use ByCerfrance\LlmApiLib\Tool\JsonSchemaArgumentsValidator;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Client\ClientInterface;

#[CoversClass(Mistral::class)]
#[CoversClass(MistralChatBuilder::class)]
#[UsesClass(Chat::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(Model::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(ChatPayload::class)]
#[UsesClass(ModelChatBuilder::class)]
#[UsesClass(PayloadBuilder::class)]
#[UsesClass(JsonTransport::class)]
#[UsesClass(ChatContentBuilder::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(JsonSchemaArgumentsValidator::class)]
#[UsesClass(ToolExecutor::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(SystemMessage::class)]
#[UsesClass(FinishReason::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(TokenUsage::class)]
class MistralTest extends OpenAiCompatibleTestCase
{
    protected function createProvider(ClientInterface $client): AbstractOpenAiCompatible
    {
        return new Mistral('key', $client);
    }

    protected function getEndpoint(): string
    {
        return 'https://api.mistral.ai/v1/chat/completions';
    }

    protected function getExpectedId(): string
    {
        return 'Mistral';
    }

    protected function getTokenField(): string
    {
        return 'max_tokens';
    }

    public function testMaximumTokensMapping(): void
    {
        $completion = new ChatPayload(['messages' => [new Message('hello')], 'model' => 'mistral-large', 'max_completion_tokens' => 500]);

        $payload = (new PayloadBuilder([new MistralChatBuilder()]))->build($completion);

        $this->assertArrayHasKey('max_tokens', $payload);
        $this->assertArrayNotHasKey('max_completion_tokens', $payload);
        $this->assertSame(500, $payload['max_tokens']);
    }

    public function testRootMappingsDoNotRewriteSchemaProperties(): void
    {
        $schema = ['max_completion_tokens' => ['type' => 'integer'], 'service_tier' => ['type' => 'string']];
        $builder = new PayloadBuilder([new MistralChatBuilder()]);
        $result = $builder->build(new ChatPayload([
            'max_completion_tokens' => 12,
            'reasoning_effort' => ReasoningEffort::HIGH,
            'tool_choice' => ToolChoice::REQUIRED,
            'response_format' => ['schema' => $schema],
        ]));
        $this->assertSame(12, $result['max_tokens']);
        $this->assertArrayNotHasKey('max_completion_tokens', $result);
        $this->assertSame('high', $result['reasoning_effort']);
        $this->assertSame('any', $result['tool_choice']);
        $this->assertSame($schema, $result['response_format']['schema']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function incompatibleOptions(): iterable
    {
        yield 'service tier' => [['service_tier' => 'flex']];
        yield 'reasoning degradation' => [['reasoning_effort' => 'medium']];
        yield 'duplicate maximum' => [['max_completion_tokens' => 20, 'max_tokens' => 10]];
        yield 'invalid native maximum' => [['max_tokens' => -1]];
    }

    #[DataProvider('incompatibleOptions')]
    public function testIncompatibleExplicitOptionsFailBeforeHttp(array $options): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('sendRequest');
        $this->expectException(InvalidArgumentException::class);
        (new Chat(new Mistral('key', $client, extraBody: $options), new Model('model')))->chat('Hi');
    }
}
