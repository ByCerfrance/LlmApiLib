<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Conversation\ConversationInterface;
use InvalidArgumentException;
use Override;

readonly class ChatRequest implements ChatRequestInterface
{
    public function __construct(
        private ConversationInterface $conversation,
        private ?int $maxTokens = null,
        private int|float|null $temperature = null,
        private int|float|null $topP = null,
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
        return new self($conversation, $this->maxTokens, $this->temperature, $this->topP);
    }

    #[Override]
    public function getMaxTokens(): ?int
    {
        return $this->maxTokens;
    }

    #[Override]
    public function withMaxTokens(?int $maxTokens): ChatRequestInterface
    {
        return new self($this->conversation, $maxTokens, $this->temperature, $this->topP);
    }

    #[Override]
    public function getTemperature(): int|float|null
    {
        return $this->temperature;
    }

    #[Override]
    public function withTemperature(int|float|null $temperature): ChatRequestInterface
    {
        return new self($this->conversation, $this->maxTokens, $temperature, $this->topP);
    }

    #[Override]
    public function getTopP(): int|float|null
    {
        return $this->topP;
    }

    #[Override]
    public function withTopP(int|float|null $topP): ChatRequestInterface
    {
        return new self($this->conversation, $this->maxTokens, $this->temperature, $topP);
    }
}
