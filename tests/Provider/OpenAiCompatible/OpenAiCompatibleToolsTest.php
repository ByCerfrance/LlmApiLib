<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider\OpenAiCompatible;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Chat\ToolChoice;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\ModelChatBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\JsonTransport;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\ChatContentBuilder;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\Generic;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Tool\AbstractTool;
use ByCerfrance\LlmApiLib\Tool\InvocationRegistry;
use ByCerfrance\LlmApiLib\Tool\JsonSchemaArgumentsValidator;
use ByCerfrance\LlmApiLib\Tool\Tool;
use ByCerfrance\LlmApiLib\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Tool\ToolSchema;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Tool calls and results on the OpenAI-compatible wire format.
 */
#[CoversClass(AbstractOpenAiCompatible::class)]
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
#[UsesClass(Generic::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(ProviderException::class)]
#[UsesClass(AbstractTool::class)]
#[UsesClass(JsonSchemaArgumentsValidator::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolCollection::class)]
#[UsesClass(ToolExecutor::class)]
#[UsesClass(ToolSchema::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(FinishReason::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(InvocationRegistry::class)]
#[UsesClass(ToolResult::class)]
#[UsesClass(TokenUsage::class)]
class OpenAiCompatibleToolsTest extends TestCase
{
    public function testToolRoundTripSerialization(): void
    {
        $tool = new Tool('calculate', 'Calculate', ['type' => 'object', 'properties' => ['n' => ['type' => 'integer']]],
            static fn(array $args): string => (string) ($args['n'] * 2));
        $requests = [];
        $chat = $this->chat([$this->toolTurn([['one', '{"n":2}']]), $this->finalTurn()], $requests);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), toolChoice: ToolChoice::REQUIRED));
        $this->assertSame('required', $requests[0]['tool_choice']);
        $this->assertSame('calculate', $requests[0]['tools'][0]['function']['name']);
        $this->assertSame('auto', $requests[1]['tool_choice']);
        $this->assertSame(['user', 'assistant', 'tool'], array_column($requests[1]['messages'], 'role'));
        $this->assertSame('Calculating', $requests[1]['messages'][1]['content']);
        $this->assertSame('{"n":2}', $requests[1]['messages'][1]['tool_calls'][0]['function']['arguments']);
        $this->assertSame('signature', $requests[1]['messages'][1]['tool_calls'][0]['extra_content']['google']['thought_signature']);
        $this->assertSame('one', $requests[1]['messages'][2]['tool_call_id']);
        $this->assertSame('4', $requests[1]['messages'][2]['content']);
    }

    public function testSeveralCallsInOneTurnAndOriginalArgumentsArePreserved(): void
    {
        $tool = new Tool('calculate', 'Calculate', [], static fn(): string => 'done');
        $requests = [];
        $chat = $this->chat([$this->toolTurn([['one', '{"options":{}}'], ['two', '{}']]), $this->finalTurn()], $requests);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool), parallelToolCalls: true));
        $this->assertTrue($requests[0]['parallel_tool_calls']);
        $this->assertSame(['user', 'assistant', 'tool', 'tool'], array_column($requests[1]['messages'], 'role'));
        $this->assertSame('{"options":{}}', $requests[1]['messages'][1]['tool_calls'][0]['function']['arguments']);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedArguments(): iterable
    {
        yield 'invalid JSON' => ['{'];
        yield 'empty string' => [''];
        yield 'array instead of object' => ['[]'];
        yield 'scalar' => ['42'];
    }

    #[DataProvider('malformedArguments')]
    public function testMalformedArgumentsNeverExecute(string $arguments): void
    {
        $tool = new Tool('calculate', 'Calculate', [], fn(): never => $this->fail('Malformed JSON must never execute.'));
        $requests = [];
        $chat = $this->chat([$this->toolTurn([['one', $arguments]])], $requests);
        $this->expectException(RuntimeException::class);
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool)));
    }

    public function testDuplicateIdsInOneResponseAreRejectedBeforeAnyExecution(): void
    {
        $tool = new Tool('calculate', 'Calculate', [], fn(): never => $this->fail('Duplicate IDs must never execute.'));
        $requests = [];
        $chat = $this->chat([$this->toolTurn([['one', '{}'], ['one', '{}']])], $requests);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate tool invocation ID');
        $chat->chat(new ChatRequest(new Conversation(['Calculate']), tools: new ToolCollection($tool)));
    }

    /** @param list<array<string, mixed>> $responses */
    private function chat(array $responses, array &$requests): Chat
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(count($responses)))->method('sendRequest')->willReturnCallback(
            static function (RequestInterface $request) use (&$responses, &$requests): ResponseInterface {
                $requests[] = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);

                return new Response(json_encode(array_shift($responses), JSON_THROW_ON_ERROR));
            },
        );

        return new Chat(new Generic('https://example.test/chat', 'key', $client), new Model('tools'));
    }

    /** @param list<array{string, string}> $calls Identifier and JSON arguments of each call. */
    private function toolTurn(array $calls): array
    {
        $toolCalls = array_map(static fn(array $call): array => [
            'id' => $call[0], 'type' => 'function', 'function' => ['name' => 'calculate', 'arguments' => $call[1]],
            'extra_content' => ['google' => ['thought_signature' => 'signature']],
        ], $calls);

        return ['choices' => [['message' => ['role' => 'assistant', 'content' => 'Calculating', 'tool_calls' => $toolCalls], 'finish_reason' => 'tool_calls']]];
    }

    private function finalTurn(): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => 'Done'], 'finish_reason' => 'stop']]];
    }
}
