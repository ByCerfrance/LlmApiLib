<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Guard;

use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use RuntimeException;

/**
 * Exception thrown when a Guard rejects an LLM response.
 *
 * Carries the rejected response so the caller can inspect or use the partial result.
 */
class GuardException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly CompletionResponseInterface $response,
    ) {
        parent::__construct($message);
    }

    /**
     * Get the rejected response.
     *
     * @return CompletionResponseInterface
     */
    public function getResponse(): CompletionResponseInterface
    {
        return $this->response;
    }
}
