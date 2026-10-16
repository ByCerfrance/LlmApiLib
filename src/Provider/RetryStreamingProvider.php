<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Chat\Streaming\StreamControl;
use ByCerfrance\LlmApiLib\Resilience\RetryPolicy;
use ByCerfrance\LlmApiLib\Resilience\RetryRunner;
use Override;

readonly class RetryStreamingProvider implements StreamingChatProviderInterface
{
    public function __construct(
        private StreamingChatProviderInterface $provider,
        private RetryPolicy $policy = new RetryPolicy(),
        private RetryRunner $runner = new RetryRunner(),
    ) {
    }

    #[Override]
    public function getId(): string { return $this->provider->getId(); }

    #[Override]
    public function complete(ProviderChatRequestInterface $request): ChatResultInterface
    {
        return (new RetryProvider($this->provider, $this->policy, $this->runner))->complete($request);
    }

    #[Override]
    public function stream(ProviderChatRequestInterface $request, StreamControl $control): iterable
    {
        $emitted = false;
        for ($attempt = 1; !$control->isClosed(); $attempt++) {
            try {
                foreach ($this->provider->stream($request, $control) as $event) {
                    $emitted = true;
                    yield $event;
                    if ($control->isClosed()) { return; }
                }
                return;
            } catch (ProviderException $failure) {
                $delay = $this->policy->delay($attempt, $failure);
                if ($emitted || $control->isClosed() || $delay === null) { throw $failure; }
                $this->runner->wait($delay);
            }
        }
    }
}
