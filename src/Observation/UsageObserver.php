<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Observation;

use ByCerfrance\LlmApiLib\Usage\UsageCollector;
use Override;

readonly class UsageObserver implements ExecutionObserverInterface
{
    public function __construct(private UsageCollector $collector)
    {
    }

    #[Override]
    public function observe(ExecutionEvent $event): void
    {
        if (in_array($event->phase, ['provider.completed', 'provider.failed'], true) && $event->usage !== null) {
            $this->collector->add($event->usage);
        }
    }
}
