<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Resilience;

use Closure;
use Throwable;

/**
 * Retry loop shared by the retry decorators; each decorator decides what is replayable.
 *
 * Inject a custom sleep to observe or skip delays, e.g. in tests.
 */
readonly class RetryRunner
{
    /** @param Closure(int): void|null $sleep Receives milliseconds. */
    public function __construct(private ?Closure $sleep = null)
    {
    }

    /**
     * @template T
     * @param Closure(): T $operation
     * @param Closure(Throwable, int): ?int $delay
     * @return T
     */
    public function run(Closure $operation, Closure $delay): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $operation();
            } catch (Throwable $exception) {
                $wait = $delay($exception, $attempt);
                if ($wait === null) {
                    throw $exception;
                }
                $this->wait($wait);
            }
        }
    }

    public function wait(int $milliseconds): void
    {
        if ($this->sleep !== null) {
            ($this->sleep)($milliseconds);
        } elseif ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
