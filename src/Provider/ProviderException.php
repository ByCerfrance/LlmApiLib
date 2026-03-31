<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use RuntimeException;
use Throwable;

class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        private string $body = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
