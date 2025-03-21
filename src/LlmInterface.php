<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

use ByCerfrance\LlmApiLib\Completions\CompletionsInterface;

interface LlmInterface
{
    /**
     * Chat.
     *
     * @param string|CompletionsInterface $completions
     *
     * @return mixed
     */
    public function chat(string|CompletionsInterface $completions): CompletionsInterface;
}
