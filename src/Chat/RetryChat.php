<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Guard\GuardException;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Resilience\RetryPolicy;
use ByCerfrance\LlmApiLib\Resilience\RetryRunner;
use ByCerfrance\LlmApiLib\Tool\ToolResult;
use InvalidArgumentException;
use Override;
use Throwable;

/** Explicit whole-call retries for tool-free chats; tool conversations use RetryProvider. */
readonly class RetryChat implements ChatInterface
{
    public function __construct(
        private ChatInterface $chat,
        private RetryPolicy $policy = new RetryPolicy(),
        private RetryRunner $runner = new RetryRunner(),
        private bool $retryOnGuard = false,
    ) {
    }

    #[Override]
    public function chat(ChatRequestInterface|string $request): ChatResponseInterface
    {
        if ($request instanceof ChatRequestInterface) {
            if ($request->getTools() !== null) {
                throw new InvalidArgumentException('Whole-chat retries require a tool-free request; wrap the provider for tool conversations.');
            }
            foreach ($request->getConversation() as $message) {
                if ($message instanceof ToolResult || ($message instanceof AssistantMessage && $message->hasToolCalls())) {
                    throw new InvalidArgumentException('Whole-chat retries cannot replay tool history.');
                }
            }
        }

        return $this->runner->run(fn(): ChatResponseInterface => $this->chat->chat($request),
            function (Throwable $failure, int $attempt): ?int {
                if ($failure instanceof GuardException && $this->retryOnGuard) {
                    foreach ($failure->getResponse()->getTurns() as $turn) {
                        if ($turn->getToolCalls() !== []) {
                            return null;
                        }
                    }

                    return $this->policy->delay($attempt);
                }

                return $failure instanceof ProviderException ? $this->policy->delay($attempt, $failure) : null;
            });
    }
}
