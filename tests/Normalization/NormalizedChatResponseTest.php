<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Normalization;

use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\ChatResponseInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\ConversationInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Normalization\NormalizedChatResponse;
use ByCerfrance\LlmApiLib\Normalization\NullPromptNormalizer;
use ByCerfrance\LlmApiLib\Normalization\NullResponseNormalizer;
use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NormalizedChatResponse::class)]
#[CoversClass(NullPromptNormalizer::class)]
#[CoversClass(NullResponseNormalizer::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(TextContent::class)]
class NormalizedChatResponseTest extends TestCase
{
    public function testNullNormalizersPreserveIdentity(): void
    {
        $request = $this->createStub(ChatRequestInterface::class);
        $response = $this->createStub(ChatResponseInterface::class);

        self::assertSame($request, (new NullPromptNormalizer())->normalize($request));
        self::assertSame($response, (new NullResponseNormalizer())->normalize($response));
    }

    public function testNormalizedOutputPreservesOriginalContinuationAndAccounting(): void
    {
        $conversation = $this->createStub(ConversationInterface::class);
        $continuation = $this->createStub(ChatRequestInterface::class);
        $usage = $this->createStub(TokenUsageInterface::class);
        $originalResult = $this->createStub(ChatResultInterface::class);
        $original = $this->createMock(ChatResponseInterface::class);
        $original->method('toConversation')->willReturn($conversation);
        $original->method('getTurns')->willReturn([$originalResult]);
        $original->method('getUsage')->willReturn($usage);
        $original->expects(self::once())->method('reply')->with('Continue', 'please')->willReturn($continuation);

        $message = $this->createStub(MessageInterface::class);
        $message->method('getContent')->willReturn(new TextContent('{"answer":42}'));
        $normalizedResult = $this->createStub(ChatResultInterface::class);
        $normalizedResult->method('getMessage')->willReturn($message);
        $response = new NormalizedChatResponse($original, $normalizedResult);

        self::assertSame('{"answer":42}', $response->getText());
        self::assertSame($normalizedResult, $response->getResult());
        self::assertSame($original, $response->getOriginalResponse());
        self::assertSame($conversation, $response->toConversation());
        self::assertSame($continuation, $response->reply('Continue', 'please'));
        self::assertSame([$originalResult], $response->getTurns());
        self::assertSame($usage, $response->getUsage());
    }
}
