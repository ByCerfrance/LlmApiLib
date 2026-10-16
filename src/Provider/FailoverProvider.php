<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\ProviderAssistantMessage;
use InvalidArgumentException;
use Override;

/** Ordered failover of a single model turn, never a whole conversation restart. */
readonly class FailoverProvider implements ChatProviderInterface
{
    /** @param non-empty-list<ChatProviderInterface> $providers Endpoints must accept the configured model name. */
    public function __construct(private array $providers, private string $id = 'failover')
    {
        if ($providers === []) {
            throw new InvalidArgumentException('Failover requires at least one provider.');
        }
        foreach ($providers as $provider) {
            if (!$provider instanceof ChatProviderInterface) {
                throw new InvalidArgumentException('Failover entries must implement ChatProviderInterface.');
            }
        }
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
