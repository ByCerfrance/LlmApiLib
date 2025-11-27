<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;
use Override;
use RuntimeException;

readonly class Retry implements LlmInterface
{
    public function __construct(
        private LlmInterface $provider,
        private int $time = 5000,
        private int $retry = 2,
    ) {
    }

    #[Override]
    public function chat(CompletionInterface|string $completion): CompletionResponseInterface
    {
        $firstException = null;

        for ($i = 0; $i < max(1, $this->retry); $i++) {
            try {
                return $this->provider->chat($completion);
            } catch (RuntimeException $exception) {
                $firstException ??= $exception;
            }
            usleep($this->time * 1000);
        }

        throw $firstException;
    }

    #[Override]
    public function getUsage(): UsageInterface
    {
        return $this->provider->getUsage();
    }

    #[Override]
    public function getCapabilities(): array
    {
        return $this->provider->getCapabilities();
    }

    #[Override]
    public function supports(Capability $capability, Capability ...$_capability): bool
    {
        return $this->provider->supports($capability, ...$_capability);
    }
}
