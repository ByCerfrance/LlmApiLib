<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider\OpenAi;

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
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\ModelChatBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\JsonTransport;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAi;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAiChatBuilder;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\ChatContentBuilder;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Tests\Provider\OpenAiCompatible\OpenAiCompatibleTestCase;
use ByCerfrance\LlmApiLib\Tool\JsonSchemaArgumentsValidator;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Client\ClientInterface;

#[CoversClass(OpenAi::class)]
#[CoversClass(OpenAiChatBuilder::class)]
#[UsesClass(Chat::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(SystemMessage::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(FinishReason::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(Model::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(ChatPayload::class)]
#[UsesClass(ModelChatBuilder::class)]
#[UsesClass(PayloadBuilder::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(JsonTransport::class)]
#[UsesClass(ChatContentBuilder::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(JsonSchemaArgumentsValidator::class)]
#[UsesClass(ToolExecutor::class)]
#[UsesClass(TokenUsage::class)]
class OpenAiTest extends OpenAiCompatibleTestCase
{
    protected function createProvider(ClientInterface $client): AbstractOpenAiCompatible
    {
        return new OpenAi('key', $client);
    }

    protected function getEndpoint(): string
    {
        return 'https://api.openai.com/v1/chat/completions';
    }

    protected function getExpectedId(): string
    {
        return 'OpenAi';
    }
}
