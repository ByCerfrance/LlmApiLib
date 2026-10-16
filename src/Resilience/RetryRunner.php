<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Resilience;

use Closure;
use Throwable;

/** @internal Shared bounded retry mechanics; typed decorators decide what is replayable. */
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
                if ($this->sleep !== null) {
                    ($this->sleep)($wait);
                } elseif ($wait > 0) {
                    usleep($wait * 1000);
                }
            }
        }
    }
}
