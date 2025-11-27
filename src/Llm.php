<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
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
            yield from $this->providers;
            return;
        }

        foreach ($this->providers as $provider) {
            $diff = array_udiff(
                $completion->requiredCapabilities(),
                $provider->getCapabilities(),
                fn(Capability $a, Capability $b) => strcmp($a->name, $b->name)
            );

            if (true === empty($diff)) {
                yield $provider;
            }
        }
    }

    #[Override]
    public function chat(CompletionInterface|string $completion): CompletionResponseInterface
    {
        foreach ($this->getProviders($completion) as $provider) {
            try {
                return $provider->chat($completion);
            } catch (Throwable $exception) {
            }
        }

        throw $exception ?? throw new RuntimeException(
            sprintf(
                'No LLM provider compatible with the given completion (required capabilities: %s)',
                implode(', ', $completion->requiredCapabilities())
            )
        );
    }

    #[Override]
    public function getUsage(): UsageInterface
    {
        $usage = new Usage();

        array_walk(
            $this->providers,
            fn(LlmInterface $provider) => $usage->addUsage($provider->getUsage()),
        );

        return $usage;
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
