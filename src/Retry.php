<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
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
}
