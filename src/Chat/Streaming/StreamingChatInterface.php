<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat\Streaming;

use ByCerfrance\LlmApiLib\Chat\ChatInterface;
use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;

interface StreamingChatInterface extends ChatInterface
{
    /** @param ChatRequestInterface|string $request @return ChatStream Close explicitly when stopping consumption early. */
    public function stream(ChatRequestInterface|string $request): ChatStream;
}
