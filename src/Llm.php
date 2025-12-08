<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Usage\Usage;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;
use Override;
use RuntimeException;
use Throwable;

readonly class Llm implements LlmInterface
{
    private array $providers;

    public function __construct(
        LlmInterface ...$provider,
    ) {
        $this->providers = $provider ?: throw new RuntimeException('No provider given');
    }

    /**
     * Get a provider list, compatible with the given completion, if any.
     *
     * @param CompletionInterface|null $completion
     *
     * @return LlmInterface[]
     */
    public function getProviders(?CompletionInterface $completion = null): iterable
    {
        if (null === $completion) {
            return $this->providers;
        }

        $candidates = $this->providers;

        if (false === empty($requiredCapabilities = $completion->requiredCapabilities())) {
            $candidates = array_filter(
                $candidates,
                fn(LlmInterface $provider) => $provider->supports(...$requiredCapabilities),
            );
        }

        if (null !== ($strategy = $completion->getSelectionStrategy())) {
            usort(
                $candidates,
                fn(LlmInterface $a, LlmInterface $b) => $b->getScoring($strategy) <=> $a->getScoring($strategy)
            );
        }

        $candidates = array_values($candidates);

        return $candidates;
    }

    #[Override]
    public function chat(CompletionInterface|string $completion): CompletionResponseInterface
    {
        if (is_string($completion)) {
            $completion = new Completion(messages: [new Message($completion)]);
        }

        foreach ($this->getProviders($completion) as $provider) {
            try {
                return $provider->chat($completion);
            } catch (Throwable $exception) {
            }
        }

        throw $exception ?? throw new RuntimeException(
            sprintf(
                'No LLM provider compatible with the given completion (required capabilities: %s)',
                implode(
                    ', ',
                    array_map(fn(Capability $v) => $v->value, $completion->requiredCapabilities())
                )
            )
        );
    }

    #[Override]
    public function getScoring(SelectionStrategy $strategy): float
    {
        return max(array_map(fn(LlmInterface $provider) => $provider->getScoring($strategy), $this->providers) ?: [.0]);
    }

    #[Override]
    public function getUsage(): UsageInterface
    {
        $usage = new Usage();

        foreach ($this->providers as $provider) {
            $usage->addUsage($provider->getUsage());
        }

        return $usage;
    }

    #[Override]
    public function getCost(int $precision = 4): float
    {
        $sum = array_sum(
            array_map(
                fn(LlmInterface $provider) => $provider->getCost(max($precision, 4)),
                $this->providers,
            )
        );

        return round($sum, $precision);
    }

    #[Override]
    public function getCapabilities(): array
    {
        return array_unique(
            array_merge(
                ...array_map(
                    fn(LlmInterface $provider) => $provider->getCapabilities(),
                    $this->providers,
                )
            ),
            SORT_REGULAR,
        );
    }

    #[Override]
    public function supports(Capability $capability, Capability ...$_capability): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($capability, ...$_capability)) {
                return true;
            }
        }

        return false;
    }
}
