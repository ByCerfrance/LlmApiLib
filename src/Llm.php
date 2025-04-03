<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
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
        $this->providers = $provider;
    }

    #[Override]
    public function chat(CompletionInterface|string $completion): CompletionInterface
    {
        foreach ($this->providers as $provider) {
            try {
                return $provider->chat($completion);
            } catch (Throwable $exception) {
            }
        }

        throw $exception ?? throw new RuntimeException('No LLM provider');
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
}
