<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;

interface LlmInterface
{
    /**
     * Chat.
     *
     * @param string|CompletionInterface $completion
     *
     * @return CompletionResponseInterface
     */
    public function chat(string|CompletionInterface $completion): CompletionResponseInterface;

    /**
     * Get usage.
     *
     * @return UsageInterface
     */
    public function getUsage(): UsageInterface;

    /**
     * Get capabilities.
     *
     * @return Capability[]
     */
    public function getCapabilities(): array;
}
