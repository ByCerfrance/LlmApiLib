<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion;

use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\ResponseFormatInterface;
use Countable;
use IteratorAggregate;
use JsonSerializable;

interface CompletionInterface extends JsonSerializable, Countable, IteratorAggregate
{
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
     * @return string|null
     */
    public function getModel(): ?string;

    /**
     * With model.
     *
     * @param string|null $model
     *
     * @return CompletionInterface
     */
    public function withModel(?string $model): CompletionInterface;

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
}
