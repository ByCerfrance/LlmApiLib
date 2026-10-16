<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Completion\FinishReason;
use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;

interface ChatResultInterface
{
    /** @return MessageInterface Generated assistant message. */
    public function getMessage(): MessageInterface;

    /** @return TokenUsageInterface Usage for this provider turn only. */
    public function getUsage(): TokenUsageInterface;

    /** @return FinishReason|null Normalized finish reason when recognized. */
    public function getFinishReason(): ?FinishReason;

    /** @return string Configured provider connection ID. */
    public function getProviderId(): string;

    /** @return string|null Actual model identifier reported by the provider. */
    public function getModelName(): ?string;

    /** @return string|null Provider HTTP request identifier, if supplied. */
    public function getRequestId(): ?string;
}
