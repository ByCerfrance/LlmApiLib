<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Message;

use ByCerfrance\LlmApiLib\Completion\Content\ContentInterface;
use JsonSerializable;

interface MessageInterface extends JsonSerializable
{
    /**
     * Get role of message.
     *
     * @return RoleEnum
     */
    public function getRole(): RoleEnum;

    /**
     * Get message content.
     *
     * @return ContentInterface
     */
    public function getContent(): ContentInterface;
}
