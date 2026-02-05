<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion;

use ArrayIterator;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\ResponseFormatInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use Override;
use Traversable;

readonly class Completion implements CompletionInterface
{
    protected array $messages;

    public function __construct(
        array $messages,
        protected ?ResponseFormatInterface $responseFormat = null,
        protected ModelInfo|string|null $model = null,
        protected int $maxTokens = 1000,
        protected int|float $temperature = 1,
        protected int|float $top_p = 1,
        protected int|null $seed = null,
        protected ?SelectionStrategy $selectionStrategy = null,
        protected ?ToolCollection $tools = null,
        protected int $maxToolIterations = 10,
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
    public function getResponseFormat(): ?ResponseFormatInterface
    {
        return $this->responseFormat;
    }

    #[Override]
    public function withResponseFormat(?ResponseFormatInterface $responseFormat): CompletionInterface
    {
        return new Completion(
            messages: $this->messages,
            responseFormat: $responseFormat,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            top_p: $this->top_p,
            seed: $this->seed,
            selectionStrategy: $this->selectionStrategy,
            tools: $this->tools,
            maxToolIterations: $this->maxToolIterations,
        );
    }

    #[Override]
    public function getModel(): ModelInfo|string|null
    {
        return $this->model;
    }

    #[Override]
    public function withModel(ModelInfo|string|null $model): CompletionInterface
    {
        return new Completion(
            messages: $this->messages,
            responseFormat: $this->responseFormat,
            model: $model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            top_p: $this->top_p,
            seed: $this->seed,
            selectionStrategy: $this->selectionStrategy,
            tools: $this->tools,
            maxToolIterations: $this->maxToolIterations,
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
            responseFormat: $this->responseFormat,
            model: $this->model,
            maxTokens: $maxTokens,
            temperature: $this->temperature,
            top_p: $this->top_p,
            seed: $this->seed,
            selectionStrategy: $this->selectionStrategy,
            tools: $this->tools,
            maxToolIterations: $this->maxToolIterations,
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
            responseFormat: $this->responseFormat,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $temperature,
            top_p: $this->top_p,
            seed: $this->seed,
            selectionStrategy: $this->selectionStrategy,
            tools: $this->tools,
            maxToolIterations: $this->maxToolIterations,
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
            responseFormat: $this->responseFormat,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            top_p: $topP,
            seed: $this->seed,
            selectionStrategy: $this->selectionStrategy,
            tools: $this->tools,
            maxToolIterations: $this->maxToolIterations,
        );
    }

    #[Override]
    public function getSeed(): int|null
    {
        return $this->seed;
    }

    #[Override]
    public function withSeed(int|null $seed): CompletionInterface
    {
        return new Completion(
            messages: $this->messages,
            responseFormat: $this->responseFormat,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            top_p: $this->top_p,
            seed: $seed,
            selectionStrategy: $this->selectionStrategy,
            tools: $this->tools,
            maxToolIterations: $this->maxToolIterations,
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
        return array_filter(
            [
                "max_tokens" => $this->maxTokens,
                "messages" => $this->messages,
                "model" => null !== $this->model ? (string)$this->model : null,
                "response_format" => $this->responseFormat,
                "stream" => false,
                "temperature" => $this->temperature,
                "top_p" => $this->top_p,
                "seed" => $this->seed,
                "tools" => $this->tools,
            ],
            fn($v) => null !== $v,
        );
    }

    #[Override]
    public function getLastMessage(?RoleEnum $role = null): ?MessageInterface
    {
        $nbMessage = count($this->messages);
        for ($i = $nbMessage - 1; $i >= 0; $i--) {
            if (null === $role || $role === $this->messages[$i]->getRole()) {
                return $this->messages[$i];
            }
        }

        return null;
    }

    #[Override]
    public function withNewMessage(MessageInterface|string $message): CompletionInterface
    {
        if (is_string($message)) {
            $message = new Message($message);
        }

        return new Completion(
            messages: [...$this->messages, $message],
            responseFormat: $this->responseFormat,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            top_p: $this->top_p,
            seed: $this->seed,
            selectionStrategy: $this->selectionStrategy,
            tools: $this->tools,
            maxToolIterations: $this->maxToolIterations,
        );
    }

    #[Override]
    public function getSelectionStrategy(): ?SelectionStrategy
    {
        return $this->selectionStrategy;
    }

    #[Override]
    public function withSelectionStrategy(SelectionStrategy|null $strategy): CompletionInterface
    {
        return new Completion(
            messages: $this->messages,
            responseFormat: $this->responseFormat,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            top_p: $this->top_p,
            seed: $this->seed,
            selectionStrategy: $strategy,
            tools: $this->tools,
            maxToolIterations: $this->maxToolIterations,
        );
    }

    #[Override]
    public function getTools(): ?ToolCollection
    {
        return $this->tools;
    }

    #[Override]
    public function withTools(ToolCollection|ToolInterface|null ...$tools): CompletionInterface
    {
        $tools = array_filter($tools, fn($t) => null !== $t);

        if (empty($tools)) {
            $collection = null;
        } elseif (count($tools) === 1 && $tools[0] instanceof ToolCollection) {
            $collection = $tools[0];
        } else {
            $flatTools = [];
            foreach ($tools as $tool) {
                if ($tool instanceof ToolCollection) {
                    foreach ($tool as $t) {
                        $flatTools[] = $t;
                    }
                } else {
                    $flatTools[] = $tool;
                }
            }
            $collection = new ToolCollection(...$flatTools);
        }

        return new Completion(
            messages: $this->messages,
            responseFormat: $this->responseFormat,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            top_p: $this->top_p,
            seed: $this->seed,
            selectionStrategy: $this->selectionStrategy,
            tools: $collection,
            maxToolIterations: $this->maxToolIterations,
        );
    }

    #[Override]
    public function getMaxToolIterations(): int
    {
        return $this->maxToolIterations;
    }

    #[Override]
    public function withMaxToolIterations(int $maxIterations): CompletionInterface
    {
        return new Completion(
            messages: $this->messages,
            responseFormat: $this->responseFormat,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            top_p: $this->top_p,
            seed: $this->seed,
            selectionStrategy: $this->selectionStrategy,
            tools: $this->tools,
            maxToolIterations: $maxIterations,
        );
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        $capabilities = array_merge(
            $this->responseFormat?->requiredCapabilities() ?? [],
            ...array_map(
                fn(MessageInterface $message) => $message->requiredCapabilities(),
                $this->messages,
            ),
        );

        if (null !== $this->tools && count($this->tools) > 0) {
            $capabilities[] = Capability::TOOLS;
        }

        return array_unique($capabilities, SORT_REGULAR);
    }
}
