<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Observation\ExecutionEvent;
use ByCerfrance\LlmApiLib\Observation\ExecutionObserverInterface;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use ByCerfrance\LlmApiLib\Usage\UsageRecord;
use Override;
use Throwable;

readonly class ObservedProvider implements ChatProviderInterface
{
    public function __construct(private ChatProviderInterface $provider, private ExecutionObserverInterface $observer)
    {
    }

    #[Override]
    public function getId(): string
    {
        return $this->provider->getId();
    }

    #[Override]
    public function complete(ProviderChatRequestInterface $request): ChatResultInterface
    {
        $id = bin2hex(random_bytes(16));
        $this->observer->observe(new ExecutionEvent($id, 'provider.started', $this->getId()));
        try {
            $result = $this->provider->complete($request);
        } catch (Throwable $failure) {
            $usage = $failure instanceof ProviderException ? $failure->getReportedUsage() : null;
            $record = UsageRecord::fromTokens($id, $usage ?? new TokenUsage(), $this->getId(), $request->getModel()->getName());
            $this->observer->observe(new ExecutionEvent($id, 'provider.failed', $this->getId(), $record,
                $failure instanceof ProviderException ? $failure->getKind()->value : 'unknown'));
            throw $failure;
        }
        $this->observer->observe(new ExecutionEvent($id, 'provider.completed', $this->getId(),
            UsageRecord::fromTokens($id, $result->getUsage(), $result->getProviderId(), $result->getModelName())));

        return $result;
    }
}
