<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion;

use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\ResponseFormatInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolInterface;
use ByCerfrance\LlmApiLib\Model\CapabilityRequirement;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use Countable;
use IteratorAggregate;
use JsonSerializable;

interface CompletionInterface extends CapabilityRequirement, JsonSerializable, Countable, IteratorAggregate
{
    /**
     * Get tools.
     *
     * @return ToolCollection|null
     */
    public function getTools(): ?ToolCollection;

    /**
     * With tools.
     *
     * @param ToolCollection|ToolInterface|null ...$tools
     *
     * @return CompletionInterface
     */
    public function withTools(ToolCollection|ToolInterface|null ...$tools): CompletionInterface;

    /**
     * Get max tool iterations.
     *
     * @return int
     */
    public function getMaxToolIterations(): int;

    /**
     * With max tool iterations.
     *
     * @param int $maxIterations
     *
     * @return CompletionInterface
     */
    public function withMaxToolIterations(int $maxIterations): CompletionInterface;

    /**
     * Get response format.
     *
     * @return ResponseFormatInterface|null
     */
    public function getResponseFormat(): ?ResponseFormatInterface;

    /**
     * With response format.
     *
     * @param ResponseFormatInterface|null $responseFormat
     *
     * @return CompletionInterface
     */
    public function withResponseFormat(?ResponseFormatInterface $responseFormat): CompletionInterface;

    /**
     * Get model.
     *
     * @return ModelInfo|string|null
     */
    public function getModel(): ModelInfo|string|null;

    /**
     * With model.
     *
     * @param ModelInfo|string|null $model
     *
     * @return CompletionInterface
     */
    public function withModel(ModelInfo|string|null $model): CompletionInterface;

    /**
     * Get max tokens.
     *
     * @return int
     */
    public function getMaxTokens(): int;

    /**
     * With max tokens.
     *
     * @param int $maxTokens
     *
     * @return CompletionInterface
     */
    public function withMaxTokens(int $maxTokens): CompletionInterface;

    /**
     * Get temperature.
     *
     * @return int|float
     */
    public function getTemperature(): int|float;

    /**
     * With temperature.
     *
     * @param int|float $temperature
     *
     * @return CompletionInterface
     */
    public function withTemperature(int|float $temperature): CompletionInterface;

    /**
     * Get top p.
     *
     * @return int|float
     */
    public function getTopP(): int|float;

    /**
     * With top p.
     *
     * @param int|float $topP
     *
     * @return CompletionInterface
     */
    public function withTopP(int|float $topP): CompletionInterface;

    /**
     * Get seed.
     *
     * @return int|null
     */
    public function getSeed(): int|null;

    /**
     * With seed.
     *
     * @param int|null $seed
     *
     * @return CompletionInterface
     */
    public function withSeed(int|null $seed): CompletionInterface;

    /**
     * Get last message.
     *
     * @param RoleEnum|null $role
     *
     * @return MessageInterface|null
     */
    public function getLastMessage(?RoleEnum $role = null): ?MessageInterface;

    /**
     * With new message.
     *
     * @param MessageInterface|string $message
     *
     * @return CompletionInterface
     */
    public function withNewMessage(MessageInterface|string $message): CompletionInterface;

    /**
     * Get the model selection strategy.
     *
     * @return SelectionStrategy|null
     */
    public function getSelectionStrategy(): ?SelectionStrategy;

    /**
     * Get the model selection strategy.
     *
     * @param SelectionStrategy|null $strategy
     *
     * @return CompletionInterface
     */
    public function withSelectionStrategy(SelectionStrategy|null $strategy): CompletionInterface;
}
