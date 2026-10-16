<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Completion\FinishReason;
use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use Override;

readonly class ChatResult implements ChatResultInterface
{
    public function __construct(
        private MessageInterface $message,
        private TokenUsageInterface $usage,
        private ?FinishReason $finishReason,
        private string $providerId,
        private ?string $modelName = null,
        private ?string $requestId = null,
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
        return $this->message instanceof AssistantMessage ? array_values($this->message->getToolCalls()) : [];
    }

    #[Override]
    public function getUsage(): TokenUsageInterface
    {
        return $this->usage;
    }

    #[Override]
    public function getFinishReason(): ?FinishReason
    {
        return $this->finishReason;
    }

    #[Override]
    public function getProviderId(): string
    {
        return $this->providerId;
    }

    #[Override]
    public function getModelName(): ?string
    {
        return $this->modelName;
    }

    #[Override]
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
