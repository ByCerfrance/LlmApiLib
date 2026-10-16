<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Completion\ReasoningEffort;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\ResponseFormatInterface;
use ByCerfrance\LlmApiLib\Completion\ServiceTier;
use ByCerfrance\LlmApiLib\Conversation\ConversationInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use InvalidArgumentException;
use Override;

readonly class ChatRequest implements ChatRequestInterface
{
    public function __construct(
        private ConversationInterface $conversation,
        private ?int $maxTokens = null,
        private int|float|null $temperature = null,
        private int|float|null $topP = null,
        private ?ResponseFormatInterface $responseFormat = null,
        private ?ReasoningEffort $reasoningEffort = null,
        private ?ServiceTier $serviceTier = null,
        private ?int $seed = null,
    ) {
        if ($maxTokens !== null && $maxTokens < 1) {
            throw new InvalidArgumentException('Maximum tokens must be positive.');
        }
        if ($temperature !== null && (!is_finite((float) $temperature) || $temperature < 0)) {
            throw new InvalidArgumentException('Temperature must be finite and non-negative.');
        }
        if ($topP !== null && (!is_finite((float) $topP) || $topP < 0 || $topP > 1)) {
            throw new InvalidArgumentException('Top P must be between zero and one.');
        }
    }

    #[Override]
    public function getConversation(): ConversationInterface
    {
        return $this->conversation;
    }

    #[Override]
    public function withConversation(ConversationInterface $conversation): ChatRequestInterface
    {
        return $this->copy(['conversation' => $conversation]);
    }

    #[Override]
    public function getMaxTokens(): ?int
    {
        return $this->maxTokens;
    }

    #[Override]
    public function withMaxTokens(?int $maxTokens): ChatRequestInterface
    {
        return $this->copy(['maxTokens' => $maxTokens]);
    }

    #[Override]
    public function getTemperature(): int|float|null
    {
        return $this->temperature;
    }

    #[Override]
    public function withTemperature(int|float|null $temperature): ChatRequestInterface
    {
        return $this->copy(['temperature' => $temperature]);
    }

    #[Override]
    public function getTopP(): int|float|null
    {
        return $this->topP;
    }

    #[Override]
    public function withTopP(int|float|null $topP): ChatRequestInterface
    {
        return $this->copy(['topP' => $topP]);
    }

    #[Override]
    public function getResponseFormat(): ?ResponseFormatInterface
    {
        return $this->responseFormat;
    }

    #[Override]
    public function withResponseFormat(?ResponseFormatInterface $responseFormat): ChatRequestInterface
    {
        return $this->copy(['responseFormat' => $responseFormat]);
    }

    #[Override]
    public function getReasoningEffort(): ?ReasoningEffort
    {
        return $this->reasoningEffort;
    }

    #[Override]
    public function withReasoningEffort(?ReasoningEffort $reasoningEffort): ChatRequestInterface
    {
        return $this->copy(['reasoningEffort' => $reasoningEffort]);
    }

    #[Override]
    public function getServiceTier(): ?ServiceTier
    {
        return $this->serviceTier;
    }

    #[Override]
    public function withServiceTier(?ServiceTier $serviceTier): ChatRequestInterface
    {
        return $this->copy(['serviceTier' => $serviceTier]);
    }

    #[Override]
    public function getSeed(): ?int
    {
        return $this->seed;
    }

    #[Override]
    public function withSeed(?int $seed): ChatRequestInterface
    {
        return $this->copy(['seed' => $seed]);
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        $capabilities = [Capability::TEXT, ...($this->responseFormat?->requiredCapabilities() ?? [])];
        foreach ($this->conversation as $message) {
            array_push($capabilities, ...$message->requiredCapabilities());
        }
        if ($this->reasoningEffort !== null) {
            $capabilities[] = Capability::REASONING;
        }

        return array_values(array_unique($capabilities, SORT_REGULAR));
    }

    /** @param array<string, mixed> $changes */
    private function copy(array $changes): self
    {
        return new self(...array_replace(get_object_vars($this), $changes));
    }
}
