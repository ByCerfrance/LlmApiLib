<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Completion\Message\UserMessage;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Usage\Usage;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;
use Override;
use Psr\Log\LoggerInterface;
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
    public function chat(
        CompletionInterface|string $completion,
        ?LoggerInterface $logger = null,
    ): CompletionResponseInterface {
        if (is_string($completion)) {
            $completion = new Completion(messages: [new UserMessage($completion)]);
        }

        $candidates = $this->getProviders($completion);
        $strategy = $completion->getSelectionStrategy();

        $logger?->debug(
            'LLM routing started' . ($strategy ? ' with strategy {strategy}' : ''),
            [
                'strategy' => $strategy?->value,
                'candidates_count' => count($candidates),
                'required_capabilities' => array_map(
                    fn(Capability $c) => $c->value,
                    $completion->requiredCapabilities()
                ),
            ]
        );

        foreach ($candidates as $index => $provider) {
            if ($index === 0) {
                $logger?->debug(
                    'LLM provider selected: {provider}' . ($strategy ? ' (score: {score})' : ''),
                    [
                        'provider' => $provider::class,
                        'strategy' => $strategy?->value,
                        'score' => $strategy ? $provider->getScoring($strategy) : null,
                    ]
                );
            }

            try {
                return $provider->chat($completion, $logger);
            } catch (Throwable $exception) {
                $logger?->warning(
                    'LLM provider {provider} failed, trying next',
                    [
                        'provider' => $provider::class,
                        'exception' => $exception->getMessage(),
                        ...($exception instanceof ProviderException ? ['response_body' => $exception->getBody()] : []),
                    ]
                );
            }
        }

        $logger?->error(
            'All LLM providers failed',
            [
                'required_capabilities' => array_map(
                    fn(Capability $c) => $c->value,
                    $completion->requiredCapabilities()
                ),
            ]
        );

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
    public function getMaxContextTokens(): ?int
    {
        $values = array_filter(
            array_map(
                fn(LlmInterface $provider) => $provider->getMaxContextTokens(),
                $this->providers,
            ),
        );

        return empty($values) ? null : min($values);
    }

    #[Override]
    public function getMaxOutputTokens(): ?int
    {
        $values = array_filter(
            array_map(
                fn(LlmInterface $provider) => $provider->getMaxOutputTokens(),
                $this->providers,
            ),
        );

        return empty($values) ? null : min($values);
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
