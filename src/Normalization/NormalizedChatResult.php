<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Chat\Conversation\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use Override;

/** @internal Normalized presentation with unchanged provider metadata and tool calls. */
readonly class NormalizedChatResult implements ChatResultInterface
{
    public function __construct(
        private ChatResultInterface $original,
        private MessageInterface $message,
    ) {
    }

    #[Override]
    public function getMessage(): MessageInterface
    {
        return $this->message;
    }

    #[Override]
    public function getToolCalls(): array
    {
        return $this->original->getToolCalls();
    }

    #[Override]
    public function getUsage(): TokenUsageInterface
    {
        return $this->original->getUsage();
    }

    #[Override]
    public function getFinishReason(): ?FinishReason
    {
        return $this->original->getFinishReason();
    }

    #[Override]
    public function getProviderId(): string
    {
        return $this->original->getProviderId();
    }

    #[Override]
    public function getModelName(): ?string
    {
        return $this->original->getModelName();
    }

    #[Override]
    public function getRequestId(): ?string
    {
        return $this->original->getRequestId();
    }
}
