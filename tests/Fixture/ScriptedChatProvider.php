<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Fixture;

use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Provider\ChatProviderInterface;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequestInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use LogicException;
use Override;
use Throwable;

/**
 * Provider double replaying scripted turns and recording received requests.
 */
class ScriptedChatProvider implements ChatProviderInterface
{
    /** @var list<ProviderChatRequestInterface> */
    public array $requests = [];

    /** @param list<ChatResultInterface|Throwable> $script Turns returned (or failures thrown) in order. */
    public function __construct(private array $script = [], private readonly string $id = 'scripted')
    {
    }

    public static function answer(
        string $text = 'Done',
        ?FinishReason $finishReason = FinishReason::STOP,
        TokenUsage $usage = new TokenUsage(),
        string $providerId = 'scripted',
    ): ChatResult {
        return new ChatResult(new AssistantMessage($text), $usage, $finishReason, $providerId);
    }

    public static function toolTurn(ToolCall ...$calls): ChatResult
    {
        return new ChatResult(new AssistantMessage('Calling tools', $calls), new TokenUsage(), FinishReason::TOOL_CALLS, 'scripted');
    }

    public static function failure(FailureKind $kind, int $code = 0, ?int $retryAfterMs = null): ProviderException
    {
        return new ProviderException('Scripted failure.', $code, kind: $kind, retryAfterMs: $retryAfterMs, providerId: 'scripted');
    }

    #[Override]
    public function getId(): string
    {
        return $this->id;
    }

    #[Override]
    public function complete(ProviderChatRequestInterface $request): ChatResultInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->script) ?? throw new LogicException('No scripted turn left.');
        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }
}
