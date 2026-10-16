<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Resilience;

use ByCerfrance\LlmApiLib\Provider\ProviderException;
use InvalidArgumentException;

readonly class RetryPolicy
{
    public function __construct(
        private int $maxAttempts = 3,
        private int $initialDelayMs = 500,
        private float $multiplier = 2,
        private int $maxDelayMs = 30000,
    ) {
        if ($maxAttempts < 1 || $initialDelayMs < 0 || $maxDelayMs < 0 || $maxDelayMs > intdiv(PHP_INT_MAX, 1000)
            || !is_finite($multiplier) || $multiplier < 1) {
            throw new InvalidArgumentException('Invalid retry policy bounds.');
        }
    }

    /** Null means stop. A Retry-After beyond the bound stops rather than retrying too early. */
    public function delay(int $failedAttempt, ?ProviderException $failure = null): ?int
    {
        if ($failedAttempt >= $this->maxAttempts || ($failure !== null && !$failure->isTransient())) {
            return null;
        }
        $serverDelay = $failure?->getRetryAfterMs() ?? 0;
        if ($serverDelay > $this->maxDelayMs) {
            return null;
        }
        $backoff = (int) min($this->maxDelayMs, $this->initialDelayMs * ($this->multiplier ** max(0, $failedAttempt - 1)));

        return max($backoff, $serverDelay);
    }
}
