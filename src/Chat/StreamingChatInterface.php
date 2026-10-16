<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

interface StreamingChatInterface extends ChatInterface
{
    /** @param ChatRequestInterface|string $request @return ChatStream Close explicitly when stopping consumption early. */
    public function stream(ChatRequestInterface|string $request): ChatStream;
}
