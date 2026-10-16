<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat\Conversation\Message;

use ByCerfrance\LlmApiLib\Content\ContentInterface;

/**
 * User message.
 */
readonly class UserMessage extends Message
{
    public function __construct(string|ContentInterface $content)
    {
        parent::__construct($content, RoleEnum::USER);
    }
}
