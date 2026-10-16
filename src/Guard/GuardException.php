<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Guard;

use ByCerfrance\LlmApiLib\Chat\ChatResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when a Guard rejects an LLM response.
 *
 * Carries the rejected response so the caller can inspect or use the partial result.
 */
class GuardException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ChatResponseInterface $response,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /**
     * Get the rejected response.
     *
     * @return ChatResponseInterface
     */
    public function getResponse(): ChatResponseInterface
    {
        return $this->response;
    }
}
