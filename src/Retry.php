<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;
use Override;
use Psr\Log\LoggerInterface;
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
    public function chat(
        CompletionInterface|string $completion,
        ?LoggerInterface $logger = null,
    ): CompletionResponseInterface {
        $firstException = null;

        for ($i = 0; $i < max(1, $this->retry); $i++) {
            try {
                return $this->provider->chat($completion, $logger);
            } catch (RuntimeException $exception) {
                $firstException ??= $exception;

                $logger?->warning(
                    'LLM retry attempt {attempt}/{max_retries} failed, waiting {wait_ms}ms',
                    [
                        'attempt' => $i + 1,
                        'max_retries' => $this->retry,
                        'wait_ms' => $this->time,
                        'exception' => $exception->getMessage(),
                    ]
                );
            }
            usleep($this->time * 1000);
        }

        throw $firstException;
    }

    #[Override]
    public function getMaxContextTokens(): ?int
    {
        return $this->provider->getMaxContextTokens();
    }

    #[Override]
    public function getScoring(SelectionStrategy $strategy): float
    {
        return $this->provider->getScoring($strategy);
    }

    #[Override]
    public function getUsage(): UsageInterface
    {
        return $this->provider->getUsage();
    }

    #[Override]
    public function getCost(int $precision = 4): float
    {
        return $this->provider->getCost($precision);
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
