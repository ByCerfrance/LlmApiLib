<?php

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionResponse;
use ByCerfrance\LlmApiLib\Completion\Content\ContentFactory;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\Choices;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonSchemaFormat;
use ByCerfrance\LlmApiLib\Completion\Tool\Tool;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Provider\AbstractProvider;
use ByCerfrance\LlmApiLib\Provider\Mistral;
use ByCerfrance\LlmApiLib\Usage\Usage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\SkippedWithMessageException;

#[CoversClass(Mistral::class)]
#[CoversClass(AbstractProvider::class)]
#[UsesClass(Completion::class)]
#[UsesClass(CompletionResponse::class)]
#[UsesClass(ContentFactory::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Choices::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(JsonSchemaFormat::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolCollection::class)]
#[UsesClass(ToolResult::class)]
#[UsesClass(Capability::class)]
#[UsesClass(ModelInfo::class)]
#[UsesClass(SelectionStrategy::class)]
#[UsesClass(Usage::class)]
class MistralTest extends ProviderTestCase
{
    protected function setUp(): void
    {
        $this->sleep = 2;
        $this->provider = new Mistral(
            apiKey: getenv('MISTRAL_APIKEY') ?: throw new SkippedWithMessageException(),
            model: new ModelInfo(
                'open-mistral-7b',
                capabilities: [
                    Capability::TEXT,
                    Capability::JSON_OUTPUT,
                    Capability::JSON_SCHEMA,
                    Capability::TOOLS,
                ],
                inputCost: 10,
                outputCost: 20,
            ),
            client: new CurlAdapter(),
        );
    }
}
