<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Observation;

interface ExecutionObserverInterface
{
    /**
     * @param ExecutionEvent $event Metadata only; no prompts, tool arguments, or raw exceptions.
     * @return void Observers should not throw; failures propagate to the caller.
     */
    public function observe(ExecutionEvent $event): void;
}
