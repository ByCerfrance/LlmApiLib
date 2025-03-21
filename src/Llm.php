<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completions\CompletionsInterface;
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
    public function chat(CompletionsInterface|string $completions): CompletionsInterface
    {
        foreach ($this->providers as $provider) {
            try {
                return $provider->chat($completions);
            } catch (Throwable $exception) {
            }
        }

        throw $exception ?? throw new RuntimeException('No LLM provider');
    }
}
