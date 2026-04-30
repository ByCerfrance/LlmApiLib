<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Completion\Message\UserMessage;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Usage\Usage;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;
use Countable;
use IteratorAggregate;
use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Traversable;

/**
 * @implements IteratorAggregate<LlmInterface>
 */
readonly class Llm implements LlmInterface, IteratorAggregate, Countable
{
    private array $providers;

    public function __construct(
        LlmInterface ...$provider,
    ) {
        $this->providers = $provider ?: throw new RuntimeException('No provider given');
    }

    #[Override]
    public function getId(): string
    {
        return 'Llm';
    }

    #[Override]
    public function getLabels(): array
    {
        return array_values(
            array_unique(
                array_merge(
                    ...array_map(
                        fn(LlmInterface $provider) => $provider->getLabels(),
                        $this->providers,
                    )
                )
            )
        );
    }

    /**
     * @return LlmInterface[]
     * @deprecated Use iteration (foreach) or count() instead.
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->providers);
    }

    #[Override]
    public function count(): int
    {
        return count($this->providers);
    }

    /**
     * Filter providers.
     *
     * @param callable $callback
     *
     * @return static
     * @throws RuntimeException If no provider matches the given labels
     */
    public function filter(callable $callback): static
    {
        return new self(...array_filter($this->providers, $callback));
    }

    /**
     * Filter providers by labels.
     *
     * @param string[] $labels
     * @param bool $matchAll AND logic (true) or OR logic (false)
     *
     * @return static
     * @throws RuntimeException If no provider matches the given labels
     */
    public function filterByLabels(array $labels, bool $matchAll = true): static
    {
        if (true === empty($labels)) {
            return $this;
        }

        return $this->filter(
            fn(LlmInterface $provider) => $matchAll
                ? true === empty(array_diff($labels, $provider->getLabels()))
                : false === empty(array_intersect($labels, $provider->getLabels()))
        );
    }

    /**
     * Filter providers by required capabilities.
     *
     * @param Capability ...$capabilities
     *
     * @return static
     * @throws RuntimeException If no provider matches the given capabilities
     */
    public function filterByCapabilities(Capability ...$capabilities): static
    {
        if (true === empty($capabilities)) {
            return $this;
        }

        return $this->filter(fn(LlmInterface $provider) => $provider->supports(...$capabilities));
    }

    /**
     * Sort providers by selection strategy (descending score).
     *
     * @param SelectionStrategy|null $strategy
     *
     * @return static
     */
    public function sortByStrategy(?SelectionStrategy $strategy): static
    {
        if (null === $strategy) {
            return $this;
        }

        $sorted = $this->providers;
        usort(
            $sorted,
            fn(LlmInterface $a, LlmInterface $b) => $b->getScoring($strategy) <=> $a->getScoring($strategy),
        );

        return new self(...$sorted);
    }

    #[Override]
    public function chat(
        CompletionInterface|string $completion,
        ?LoggerInterface $logger = null,
    ): CompletionResponseInterface {
        if (is_string($completion)) {
            $completion = new Completion(messages: [new UserMessage($completion)]);
        }

        $labels = $completion->getLabels();
        $requiredCapabilities = $completion->requiredCapabilities();
        $strategy = $completion->getSelectionStrategy();

        $logger?->debug(
            'LLM routing started' . ($strategy ? ' with strategy {strategy}' : ''),
            [
                'strategy' => $strategy?->value,
                'required_capabilities' => array_values(
                    array_map(
                        fn(Capability $c) => $c->value,
                        $requiredCapabilities,
                    )
                ),
                'required_labels' => $labels,
            ]
        );

        try {
            $pool = $this
                ->filterByLabels($labels)
                ->filterByCapabilities(...$requiredCapabilities)
                ->sortByStrategy($strategy);
        } catch (RuntimeException) {
            throw new RuntimeException(
                sprintf(
                    'No LLM provider compatible with the given completion (required labels: %s, required capabilities: %s)',
                    implode(', ', $labels) ?: 'none',
                    implode(', ', array_map(fn(Capability $v) => $v->value, $requiredCapabilities)) ?: 'none',
                )
            );
        }

        $poolSize = count($pool);
        foreach ($pool as $provider) {
            $logger?->debug(
                'LLM provider selected: {provider}' . ($strategy ? ' (score: {score})' : ''),
                [
                    'provider' => $provider->getId(),
                    'strategy' => $strategy?->value,
                    'score' => $strategy ? $provider->getScoring($strategy) : null,
                    'candidates_count' => $poolSize,
                ]
            );

            try {
                return $provider->chat($completion, $logger);
            } catch (Throwable $exception) {
                $logger?->warning(
                    'LLM provider {provider} failed, trying next',
                    [
                        'provider' => $provider->getId(),
                        'exception' => $exception->getMessage(),
                        ...($exception instanceof ProviderException ? ['response_body' => $exception->getBody()] : []),
                    ]
                );
            }
        }

        $logger?->error(
            'All LLM providers failed',
            [
                'required_capabilities' => array_values(
                    array_map(
                        fn(Capability $c) => $c->value,
                        $requiredCapabilities,
                    )
                ),
                'required_labels' => $labels,
            ]
        );

        throw $exception;
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
