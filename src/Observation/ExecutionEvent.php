<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Observation;

use ByCerfrance\LlmApiLib\Usage\UsageRecord;

readonly class ExecutionEvent
{
    public function __construct(
        public string $executionId,
        public string $phase,
        public string $subject,
        public ?UsageRecord $usage = null,
        public ?string $failureKind = null,
    ) {
    }
}
