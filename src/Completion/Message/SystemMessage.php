<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Message;

use ByCerfrance\LlmApiLib\Completion\Content\ContentInterface;

/**
 * System message.
 */
readonly class SystemMessage extends Message
{
    public function __construct(string|ContentInterface $content)
    {
        parent::__construct($content, RoleEnum::SYSTEM);
    }
}
