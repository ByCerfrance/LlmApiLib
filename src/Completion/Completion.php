<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use Override;
use Traversable;

readonly class Completion implements CompletionInterface
{
    protected array $messages;

    public function __construct(
        array $messages,
        protected ?string $model = null,
        protected int $maxTokens = 1000,
        protected int|float $temperature = 1,
        protected int|float $top_p = 1,
    ) {
        $this->messages = array_map(
            fn($v) => is_string($v) ? new Message($v) : $v,
            array_filter(
                $messages,
                fn($v) => $v instanceof MessageInterface || is_string($v),
            ),
        );
    }

    #[Override]
    public function getModel(): ?string
    {
        return $this->model;
    }

    #[Override]
    public function withModel(?string $model): CompletionInterface
    {
        return new Completion(
            messages: $this->messages,
            model: $model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            top_p: $this->top_p,
        );
    }

    #[Override]
    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    #[Override]
    public function withMaxTokens(int $maxTokens): CompletionInterface
    {
        return new Completion(
            messages: $this->messages,
            model: $this->model,
            maxTokens: $maxTokens,
            temperature: $this->temperature,
            top_p: $this->top_p,
        );
    }

    #[Override]
    public function getTemperature(): int|float
    {
        return $this->temperature;
    }

    #[Override]
    public function withTemperature(int|float $temperature): CompletionInterface
    {
        return new Completion(
            messages: $this->messages,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $temperature,
            top_p: $this->top_p,
        );
    }

    #[Override]
    public function getTopP(): int|float
    {
        return $this->top_p;
    }

    #[Override]
    public function withTopP(int|float $topP): CompletionInterface
    {
        return new Completion(
            messages: $this->messages,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            top_p: $topP,
        );
    }

    #[Override]
    public function count(): int
    {
        return count($this->messages);
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->messages);
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return [
            "max_tokens" => $this->maxTokens,
            "messages" => $this->messages,
            "model" => $this->model,
            "stream" => false,
            "temperature" => $this->temperature,
            "top_p" => $this->top_p,
        ];
    }

    #[Override]
    public function getLastMessage(): ?MessageInterface
    {
        return $this->messages[count($this->messages) - 1];
    }

    #[Override]
    public function withNewMessage(MessageInterface|string $message): CompletionInterface
    {
        if (is_string($message)) {
            $message = new Message($message);
        }

        return new Completion(
            messages: [...$this->messages, $message],
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            top_p: $this->top_p,
        );
    }
}
