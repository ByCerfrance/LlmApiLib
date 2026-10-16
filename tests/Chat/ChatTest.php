<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Chat;

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
use ByCerfrance\LlmApiLib\Chat\OutputModality;
use ByCerfrance\LlmApiLib\Chat\ReasoningEffort;
use ByCerfrance\LlmApiLib\Chat\ToolChoice;
use ByCerfrance\LlmApiLib\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequestInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Tests\Fixture\ScriptedChatProvider;
use ByCerfrance\LlmApiLib\Tool\JsonSchemaArgumentsValidator;
use ByCerfrance\LlmApiLib\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Chat::class)]
#[CoversClass(ChatRequest::class)]
#[CoversClass(ChatResponse::class)]
#[CoversClass(Conversation::class)]
#[CoversClass(ChatResult::class)]
#[CoversClass(ProviderChatRequest::class)]
#[CoversClass(TokenUsage::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(SystemMessage::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(Model::class)]
#[UsesClass(JsonSchemaArgumentsValidator::class)]
#[UsesClass(ToolExecutor::class)]
#[UsesClass(ToolCollection::class)]
#[UsesClass(ProviderException::class)]
#[UsesClass(ArrayContent::class)]
#[UsesClass(ImageUrlContent::class)]
class ChatTest extends TestCase
{
    public function testTwoExchangesAndIndependentBranchPreserveSystemAndOptions(): void
    {
        $usage = new TokenUsage(10, 4, 14, 0);
        $provider = new ScriptedChatProvider([
            new ChatResult(new AssistantMessage('Answer 1'), $usage, FinishReason::STOP, 'primary', 'actual-model', 'req-test'),
            ScriptedChatProvider::answer('Answer 2', usage: $usage),
            ScriptedChatProvider::answer('Answer 3', usage: $usage),
        ], 'primary');
        $chat = new Chat($provider, new Model('requested-model'));
        $request = new ChatRequest(
            new Conversation([new SystemMessage('Reply in French.'), 'Explain crop rotation.']),
            maxTokens: 250,
            temperature: 0,
            topP: 0,
        );
        $first = $chat->chat($request);
        $second = $chat->chat($request->withConversation($first->toConversation()->withNewMessage('Give an example.')));
        $branch = $chat->chat($request->withConversation($first->toConversation()->withNewMessage('Summarize instead.')));

        $this->assertCount(2, $request->getConversation());
        $this->assertCount(3, $first->toConversation());
        $this->assertCount(5, $second->toConversation());
        $this->assertCount(5, $branch->toConversation());
        $this->assertSame(['Reply in French.', 'Explain crop rotation.', 'Answer 1', 'Give an example.'], $this->contents($provider->requests[1]));
        $this->assertSame(['Reply in French.', 'Explain crop rotation.', 'Answer 1', 'Summarize instead.'], $this->contents($provider->requests[2]));
        $this->assertSame([RoleEnum::SYSTEM, RoleEnum::USER, RoleEnum::ASSISTANT, RoleEnum::USER], array_map(
            static fn($message): RoleEnum => $message->getRole(),
            iterator_to_array($provider->requests[1]->getRequest()->getConversation(), false),
        ));
        foreach ($provider->requests as $sent) {
            $this->assertSame('requested-model', $sent->getModel()->getName());
            $this->assertSame(250, $sent->getRequest()->getMaxTokens());
            $this->assertSame(0, $sent->getRequest()->getTemperature());
            $this->assertSame(0, $sent->getRequest()->getTopP());
        }
        $this->assertSame('primary', $first->getResult()->getProviderId());
        $this->assertSame('actual-model', $first->getResult()->getModelName());
        $this->assertSame('req-test', $first->getResult()->getRequestId());
        $this->assertSame(FinishReason::STOP, $first->getResult()->getFinishReason());
        $this->assertSame(10, $first->getUsage()->getPromptTokens());
        $this->assertSame(4, $first->getUsage()->getCompletionTokens());
        $this->assertSame(14, $first->getUsage()->getTotalTokens());
        $this->assertSame(0, $first->getUsage()->getCachedTokens());
        $this->assertSame(14, $second->getUsage()->getTotalTokens());
    }

    public function testReplyKeepsOriginalOptionsAndBranches(): void
    {
        $provider = new ScriptedChatProvider([
            ScriptedChatProvider::answer('First'), ScriptedChatProvider::answer('Second'), ScriptedChatProvider::answer('Branch'),
        ]);
        $chat = new Chat($provider, new Model('model'), maxTokens: 500);
        $tools = new ToolCollection();
        $first = $chat->chat(new ChatRequest(new Conversation(['Hi']), temperature: 0, tools: $tools));
        $second = $chat->chat($first->reply('Continue.'));
        $branch = $chat->chat($first->reply(new SystemMessage('Be brief.')));

        $this->assertSame('Second', $second->getText());
        $this->assertSame(['Hi', 'First', 'Continue.', 'Second'], $this->historyContents($second));
        $this->assertSame(['Hi', 'First', 'Be brief.', 'Branch'], $this->historyContents($branch));
        $continued = $first->reply('Continue.');
        $this->assertSame(0, $continued->getTemperature());
        $this->assertSame($tools, $continued->getTools());
        $this->assertNull($continued->getMaxTokens());
        $this->assertSame(500, $provider->requests[1]->getRequest()->getMaxTokens());
    }

    public function testReplyCombinesContentsIntoOneUserMessage(): void
    {
        $first = (new Chat(new ScriptedChatProvider([ScriptedChatProvider::answer()]), new Model('model')))->chat('Hi');
        $messages = iterator_to_array($first->reply('Use this:', new TextContent('reference'))->getConversation(), false);
        $this->assertCount(3, $messages);
        $this->assertInstanceOf(UserMessage::class, $messages[2]);
        $this->assertInstanceOf(ArrayContent::class, $messages[2]->getContent());
        $this->assertCount(2, iterator_to_array($messages[2]->getContent(), false));
    }

    public function testReplyRejectsAMessageCombinedWithContents(): void
    {
        $first = (new Chat(new ScriptedChatProvider([ScriptedChatProvider::answer()]), new Model('model')))->chat('Hi');
        $this->expectException(InvalidArgumentException::class);
        $first->reply(new SystemMessage('Be brief.'), 'Continue.');
    }

    public function testGetTextJoinsTextPartsAndIgnoresOtherContents(): void
    {
        $result = static fn($content): ChatResponse => new ChatResponse(new Conversation(),
            new ChatResult(new AssistantMessage($content), new TokenUsage(), FinishReason::STOP, 'test'));
        $this->assertSame('Hello', $result('Hello')->getText());
        $this->assertSame('Hello world', $result(new ArrayContent('Hello ', new ImageUrlContent('https://example.test/a.png'), 'world'))->getText());
        $this->assertNull($result(null)->getText());
    }

    public function testResetOmitsSettingsWithoutMutatingOriginal(): void
    {
        $provider = new ScriptedChatProvider([ScriptedChatProvider::answer()]);
        $original = new ChatRequest(new Conversation(['Hi']), maxTokens: 100, temperature: 1, topP: 1);
        $reset = $original->withTemperature(null)->withTopP(null)->withMaxTokens(null);
        (new Chat($provider, new Model('model')))->chat($reset);
        $sent = $provider->requests[0]->getRequest();
        $this->assertNull($sent->getTemperature());
        $this->assertNull($sent->getTopP());
        $this->assertNull($sent->getMaxTokens());
        $this->assertSame(1, $original->getTemperature());
        $this->assertSame(1, $original->getTopP());
        $this->assertSame(100, $original->getMaxTokens());
    }

    public function testRequestOverridesDefaultsAndResetInheritsDefaults(): void
    {
        $provider = new ScriptedChatProvider([ScriptedChatProvider::answer(), ScriptedChatProvider::answer()]);
        $chat = new Chat($provider, new Model('model'), maxTokens: 500, temperature: 0.8, topP: 0.9);
        $request = new ChatRequest(new Conversation(['Hi']), temperature: 0, topP: 0);
        $chat->chat($request);
        $chat->chat($request->withTemperature(null)->withTopP(null));
        $first = $provider->requests[0]->getRequest();
        $second = $provider->requests[1]->getRequest();
        $this->assertSame(0, $first->getTemperature());
        $this->assertSame(0, $first->getTopP());
        $this->assertSame(500, $first->getMaxTokens());
        $this->assertSame(0.8, $second->getTemperature());
        $this->assertSame(0.9, $second->getTopP());
    }

    public function testRequestOptionsAreForwarded(): void
    {
        $provider = new ScriptedChatProvider([ScriptedChatProvider::answer()]);
        $tools = new ToolCollection();
        (new Chat($provider, new Model('model')))->chat(new ChatRequest(
            new Conversation(['Hi']),
            reasoningEffort: ReasoningEffort::HIGH,
            seed: 0,
            tools: $tools,
            toolChoice: ToolChoice::AUTO,
            parallelToolCalls: false,
            outputModalities: [OutputModality::TEXT],
        ));
        $sent = $provider->requests[0]->getRequest();
        $this->assertSame(ReasoningEffort::HIGH, $sent->getReasoningEffort());
        $this->assertSame(0, $sent->getSeed());
        $this->assertSame($tools, $sent->getTools());
        $this->assertSame(ToolChoice::AUTO, $sent->getToolChoice());
        $this->assertFalse($sent->getParallelToolCalls());
        $this->assertSame([OutputModality::TEXT], $sent->getOutputModalities());
    }

    public function testStringRequestAndUnknownUsage(): void
    {
        $provider = new ScriptedChatProvider([ScriptedChatProvider::answer('Hello', finishReason: null)]);
        $response = (new Chat($provider, new Model('model')))->chat('Hi');
        $messages = iterator_to_array($provider->requests[0]->getRequest()->getConversation(), false);
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        $this->assertSame('Hi', (string) $messages[0]->getContent());
        $this->assertCount(2, $response->toConversation());
        $this->assertNull($response->getUsage()->getPromptTokens());
        $this->assertNull($response->getUsage()->getCompletionTokens());
        $this->assertNull($response->getUsage()->getTotalTokens());
        $this->assertNull($response->getUsage()->getCachedTokens());
        $this->assertNull($response->getResult()->getModelName());
        $this->assertNull($response->getResult()->getRequestId());
        $this->assertNull($response->getResult()->getFinishReason());
    }

    public function testProviderFailuresDoNotBecomeResponses(): void
    {
        $failure = ScriptedChatProvider::failure(FailureKind::INVALID_RESPONSE);
        $this->expectExceptionObject($failure);
        (new Chat(new ScriptedChatProvider([$failure]), new Model('model')))->chat('Hi');
    }

    public function testInvalidDefaultsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Chat(new ScriptedChatProvider(), new Model('model'), temperature: -1);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidRequests(): iterable
    {
        yield 'maximum tokens' => [['maxTokens' => 0]];
        yield 'negative temperature' => [['temperature' => -1]];
        yield 'infinite temperature' => [['temperature' => INF]];
        yield 'top p' => [['topP' => 1.5]];
        yield 'tool iterations' => [['maxToolIterations' => -1]];
        yield 'empty modalities' => [['outputModalities' => []]];
        yield 'invalid modality' => [['outputModalities' => ['TEXT']]];
        yield 'image configuration' => [['imageConfig' => ['unknown' => 'value']]];
    }

    /** @param array<string, mixed> $options */
    #[DataProvider('invalidRequests')]
    public function testInvalidRequestOptionsAreRejected(array $options): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ChatRequest(new Conversation(['Hi']), ...$options);
    }

    public function testRequiredCapabilities(): void
    {
        $request = new ChatRequest(new Conversation(['Hi']), reasoningEffort: ReasoningEffort::LOW, tools: new ToolCollection(),
            toolChoice: ToolChoice::AUTO, outputModalities: [OutputModality::TEXT, OutputModality::IMAGE]);
        $this->assertSame([Capability::TEXT, Capability::IMAGE_OUTPUT, Capability::REASONING, Capability::TOOLS], $request->requiredCapabilities());
        $this->assertSame([Capability::TEXT], (new ChatRequest(new Conversation(['Hi'])))->requiredCapabilities());
    }

    /** @return list<string> */
    private function historyContents(ChatResponse $response): array
    {
        return array_map(
            static fn($message): string => (string) $message->getContent(),
            iterator_to_array($response->toConversation(), false),
        );
    }

    /** @return list<string> */
    private function contents(ProviderChatRequestInterface $request): array
    {
        return array_map(
            static fn($message): string => (string) $message->getContent(),
            iterator_to_array($request->getRequest()->getConversation(), false),
        );
    }
}
