<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

interface ChatInterface
{
    /**
     * @param ChatRequestInterface|string $request Strings start a new user conversation.
     * @return ChatResponseInterface A response independent of the input request.
     * @throws \InvalidArgumentException When input options are invalid.
     * @throws \RuntimeException When execution fails.
     */
    public function chat(ChatRequestInterface|string $request): ChatResponseInterface;
}
