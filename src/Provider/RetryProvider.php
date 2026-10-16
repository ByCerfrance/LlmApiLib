<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Resilience\RetryPolicy;
use ByCerfrance\LlmApiLib\Resilience\RetryRunner;
use Override;
use Throwable;

readonly class RetryProvider implements ChatProviderInterface
{
    public function __construct(
        private ChatProviderInterface $provider,
        private RetryPolicy $policy = new RetryPolicy(),
        private RetryRunner $runner = new RetryRunner(),
    ) {
    }

    #[Override]
    public function getId(): string
    {
        return $this->provider->getId();
    }

    #[Override]
    public function complete(ProviderChatRequestInterface $request): ChatResultInterface
    {
        return $this->runner->run(
            fn(): ChatResultInterface => $this->provider->complete($request),
            fn(Throwable $failure, int $attempt): ?int => $failure instanceof ProviderException ? $this->policy->delay($attempt, $failure) : null,
        );
    }
}
