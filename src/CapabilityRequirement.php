<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

interface CapabilityRequirement
{
    /**
     * Required LLM capabilities.
     *
     * @return Capability[]
     */
    public function requiredCapabilities(): array;
}
