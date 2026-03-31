<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Guard\GuardException;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;

readonly class Retry implements LlmInterface
{
    use LlmDecoratorTrait;

    public function __construct(
        private LlmInterface $provider,
        private int $time = 5000,
        private int $retry = 2,
        private float $multiplier = 1,
        private bool $retryOnGuard = false,
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
        $firstException = null;
        $waitMs = $this->time;

        for ($i = 0; $i < max(1, $this->retry); $i++) {
            try {
                return $this->provider->chat($completion, $logger);
            } catch (RuntimeException $exception) {
                if (!$this->retryOnGuard && $exception instanceof GuardException) {
                    throw $exception;
                }

                $firstException ??= $exception;
                $waitMs = (int) ($this->time * ($this->multiplier ** $i));

                $logger?->warning(
                    'LLM retry attempt {attempt}/{max_retries} failed, waiting {wait_ms}ms',
                    [
                        'attempt' => $i + 1,
                        'max_retries' => $this->retry,
                        'wait_ms' => $waitMs,
                        'exception' => $exception->getMessage(),
                        ...($exception instanceof ProviderException ? ['response_body' => $exception->getBody()] : []),
                    ]
                );
            }
            usleep($waitMs * 1000);
        }

        throw $firstException;
    }
}
