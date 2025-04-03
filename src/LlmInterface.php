<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;

interface LlmInterface
{
    /**
     * Chat.
     *
     * @param string|CompletionInterface $completion
     *
     * @return mixed
     */
    public function chat(string|CompletionInterface $completion): CompletionInterface;

    /**
     * Get usage.
     *
     * @return UsageInterface
     */
    public function getUsage(): UsageInterface;
}
