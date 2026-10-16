<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\ProviderAssistantMessage;
use InvalidArgumentException;
use Override;

/** Ordered failover of a single model turn, never a whole conversation restart. */
class FailoverProvider implements ChatProviderInterface
{
    /** @var non-empty-list<ChatProviderInterface> */
    private readonly array $providers;
    private string $id = 'failover';

    /** Endpoints must accept the configured model name; bind another model with ModelProvider. */
    public function __construct(ChatProviderInterface $provider, ChatProviderInterface ...$fallbacks)
    {
        $this->providers = [$provider, ...array_values($fallbacks)];
    }

    public function withId(string $id): static
    {
        $copy = clone $this;
        $copy->id = $id;

        return $copy;
    }

    #[Override]
    public function getId(): string
    {
        return $this->id;
    }

    #[Override]
    public function complete(ProviderChatRequestInterface $request): ChatResultInterface
    {
        $last = null;
        if (count($this->providers) > 1 && $this->hasOpaqueContinuation($request)) {
            throw new InvalidArgumentException('Opaque continuation requires a pinned provider; ordered failover cannot transfer it.');
        }
        foreach ($this->providers as $provider) {
            try {
                return $provider->complete($request);
            } catch (ProviderException $failure) {
                if (!$failure->isTransient()) {
                    throw $failure;
                }
                $last = $failure;
            }
        }

        throw $last;
    }

    private function hasOpaqueContinuation(ProviderChatRequestInterface $request): bool
    {
        foreach ($request->getRequest()->getConversation() as $message) {
            if ($message instanceof ProviderAssistantMessage) {
                return true;
            }
            if ($message instanceof AssistantMessage) {
                foreach ($message->getToolCalls() as $call) {
                    if ($call->additionalFields !== null && $call->additionalFields !== []) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
