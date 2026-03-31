<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Guard;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\LlmDecoratorTrait;
use ByCerfrance\LlmApiLib\LlmInterface;
use Closure;
use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Guard decorator for LLM responses.
 *
 * Wraps an LlmInterface and applies a guard check after each chat() call.
 * The guard callable receives the CompletionResponseInterface and should
 * throw an exception if the response does not meet the expected criteria.
 *
 * @example new Guard($provider, function (CompletionResponseInterface $r): void {
 *     if ($r->getUsage()->getTotalTokens() > 10000) {
 *         throw new \RuntimeException('Token budget exceeded');
 *     }
 * })
 */
readonly class Guard implements LlmInterface
{
    use LlmDecoratorTrait;

    /**
     * @param LlmInterface $provider The inner LLM provider to decorate
     * @param Closure(CompletionResponseInterface): void $guard Guard callable that throws on failure
     */
    public function __construct(
        private LlmInterface $provider,
        private Closure $guard,
    ) {
    }

    #[Override]
    public function getProvider(): LlmInterface
    {
        return $this->provider;
    }

    #[Override]
    public function chat(
        CompletionInterface|string $completion,
        ?LoggerInterface $logger = null,
    ): CompletionResponseInterface {
        $response = $this->provider->chat($completion, $logger);

        try {
            ($this->guard)($response);
        } catch (RuntimeException $e) {
            throw new GuardException($e->getMessage(), $response);
        }

        return $response;
    }
}
