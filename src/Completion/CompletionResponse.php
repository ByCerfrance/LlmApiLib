<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion;

use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\ResponseFormatInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollectionInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolInterface;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;
use Override;
use Traversable;

readonly class CompletionResponse implements CompletionResponseInterface
{
    public function __construct(
        private CompletionInterface $completion,
        private UsageInterface $usage,
    ) {
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return $this->completion->getIterator();
    }

    #[Override]
    public function count(): int
    {
        return $this->completion->count();
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->completion->jsonSerialize();
    }

    #[Override]
    public function getResponseFormat(): ?ResponseFormatInterface
    {
        return $this->completion->getResponseFormat();
    }

    #[Override]
    public function withResponseFormat(?ResponseFormatInterface $responseFormat): CompletionInterface
    {
        return $this->completion->withResponseFormat($responseFormat);
    }

    #[Override]
    public function getModel(): ModelInfo|string|null
    {
        return $this->completion->getModel();
    }

    #[Override]
    public function withModel(ModelInfo|string|null $model): CompletionInterface
    {
        return $this->completion->withModel($model);
    }

    #[Override]
    public function getMaxTokens(): int
    {
        return $this->completion->getMaxTokens();
    }

    #[Override]
    public function withMaxTokens(int $maxTokens): CompletionInterface
    {
        return $this->completion->withMaxTokens($maxTokens);
    }

    #[Override]
    public function getTemperature(): int|float
    {
        return $this->completion->getTemperature();
    }

    #[Override]
    public function withTemperature(float|int $temperature): CompletionInterface
    {
        return $this->completion->withTemperature($temperature);
    }

    #[Override]
    public function getTopP(): int|float
    {
        return $this->completion->getTopP();
    }

    #[Override]
    public function withTopP(float|int $topP): CompletionInterface
    {
        return $this->completion->withTopP($topP);
    }

    #[Override]
    public function getSeed(): int|null
    {
        return $this->completion->getSeed();
    }

    #[Override]
    public function withSeed(?int $seed): CompletionInterface
    {
        return $this->completion->withSeed($seed);
    }

    #[Override]
    public function getLastMessage(?RoleEnum $role = null): ?MessageInterface
    {
        return $this->completion->getLastMessage($role);
    }

    #[Override]
    public function withNewMessage(string|MessageInterface $message): CompletionInterface
    {
        return $this->completion->withNewMessage($message);
    }

    #[Override]
    public function getSelectionStrategy(): ?SelectionStrategy
    {
        return $this->completion->getSelectionStrategy();
    }

    #[Override]
    public function withSelectionStrategy(SelectionStrategy|null $strategy): CompletionInterface
    {
        return $this->completion->withSelectionStrategy($strategy);
    }

    #[Override]
    public function getTools(): ?ToolCollectionInterface
    {
        return $this->completion->getTools();
    }

    #[Override]
    public function withTools(ToolCollectionInterface|ToolInterface|null ...$tools): CompletionInterface
    {
        return $this->completion->withTools(...$tools);
    }

    #[Override]
    public function getMaxToolIterations(): int
    {
        return $this->completion->getMaxToolIterations();
    }

    #[Override]
    public function withMaxToolIterations(int $maxIterations): CompletionInterface
    {
        return $this->completion->withMaxToolIterations($maxIterations);
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        return $this->completion->requiredCapabilities();
    }

    #[Override]
    public function getUsage(): UsageInterface
    {
        return $this->usage;
    }
}
