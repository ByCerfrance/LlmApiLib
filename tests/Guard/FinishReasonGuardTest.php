<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Guard;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionResponse;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\FinishReason;
use ByCerfrance\LlmApiLib\Completion\Message\Choice;
use ByCerfrance\LlmApiLib\Completion\Message\Choices;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\Message\UserMessage;
use ByCerfrance\LlmApiLib\Guard\FinishReasonGuard;
use ByCerfrance\LlmApiLib\Guard\Guard;
use ByCerfrance\LlmApiLib\Guard\GuardException;
use ByCerfrance\LlmApiLib\LlmDecoratorTrait;
use ByCerfrance\LlmApiLib\LlmInterface;
use ByCerfrance\LlmApiLib\Usage\Usage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;

#[CoversClass(FinishReasonGuard::class)]
#[UsesClass(Guard::class)]
#[UsesClass(GuardException::class)]
#[UsesTrait(LlmDecoratorTrait::class)]
#[UsesClass(Completion::class)]
#[UsesClass(CompletionResponse::class)]
#[UsesClass(Usage::class)]
#[UsesClass(Choice::class)]
#[UsesClass(Choices::class)]
#[UsesClass(Message::class)]
#[UsesClass(UserMessage::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(FinishReason::class)]
#[UsesClass(TextContent::class)]
class FinishReasonGuardTest extends TestCase
{
    private function createResponseWithFinishReason(?FinishReason $finishReason): CompletionResponse
    {
        $choices = new Choices(
            new Choice(
                message: new Message('response', RoleEnum::ASSISTANT),
                finishReason: $finishReason,
            ),
        );

        $completion = new Completion(
            messages: [new UserMessage('hello')],
        );

        return new CompletionResponse(
            completion: $completion->withNewMessage($choices),
            usage: new Usage(),
        );
    }

    public function testPassesOnStop(): void
    {
        $response = $this->createResponseWithFinishReason(FinishReason::STOP);

        $inner = $this->createMock(LlmInterface::class);
        $inner->method('chat')->willReturn($response);

        $guard = new FinishReasonGuard($inner);
        $result = $guard->chat('hello');

        $this->assertSame($response, $result);
    }

    public function testRejectsLengthByDefault(): void
    {
        $response = $this->createResponseWithFinishReason(FinishReason::LENGTH);

        $inner = $this->createMock(LlmInterface::class);
        $inner->method('chat')->willReturn($response);

        $guard = new FinishReasonGuard($inner);

        $this->expectException(GuardException::class);
        $this->expectExceptionMessage('finish_reason is "length"');

        $guard->chat('hello');
    }

    public function testRejectsContentFilterByDefault(): void
    {
        $response = $this->createResponseWithFinishReason(FinishReason::CONTENT_FILTER);

        $inner = $this->createMock(LlmInterface::class);
        $inner->method('chat')->willReturn($response);

        $guard = new FinishReasonGuard($inner);

        $this->expectException(GuardException::class);
        $this->expectExceptionMessage('finish_reason is "content_filter"');

        $guard->chat('hello');
    }

    public function testCustomRejectedReasons(): void
    {
        $response = $this->createResponseWithFinishReason(FinishReason::STOP);

        $inner = $this->createMock(LlmInterface::class);
        $inner->method('chat')->willReturn($response);

        // Reject STOP specifically
        $guard = new FinishReasonGuard($inner, FinishReason::STOP);

        $this->expectException(GuardException::class);
        $this->expectExceptionMessage('finish_reason is "stop"');

        $guard->chat('hello');
    }

    public function testCustomRejectedAllowsLength(): void
    {
        $response = $this->createResponseWithFinishReason(FinishReason::LENGTH);

        $inner = $this->createMock(LlmInterface::class);
        $inner->method('chat')->willReturn($response);

        // Only reject CONTENT_FILTER, not LENGTH
        $guard = new FinishReasonGuard($inner, FinishReason::CONTENT_FILTER);
        $result = $guard->chat('hello');

        $this->assertSame($response, $result);
    }

    public function testPassesOnNullFinishReason(): void
    {
        $response = $this->createResponseWithFinishReason(null);

        $inner = $this->createMock(LlmInterface::class);
        $inner->method('chat')->willReturn($response);

        $guard = new FinishReasonGuard($inner);
        $result = $guard->chat('hello');

        $this->assertSame($response, $result);
    }
}
