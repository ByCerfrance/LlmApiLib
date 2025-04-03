<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Message;

use JsonSerializable;

enum RoleEnum: string implements JsonSerializable
{
    case USER = 'user';
    case ASSISTANT = 'assistant';
    case SYSTEM = 'system';
    case TOOL = 'tool';

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
