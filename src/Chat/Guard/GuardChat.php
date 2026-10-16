<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat\Guard;

use ByCerfrance\LlmApiLib\Chat\ChatInterface;
use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;
use ByCerfrance\LlmApiLib\Chat\ChatResponseInterface;
use Closure;
use Override;
use RuntimeException;

readonly class GuardChat implements ChatInterface
{
    /** @param Closure(ChatResponseInterface): void $guard */
    public function __construct(private ChatInterface $chat, private Closure $guard)
    {
    }

    #[Override]
    public function chat(ChatRequestInterface|string $request): ChatResponseInterface
    {
        $response = $this->chat->chat($request);
        try {
            ($this->guard)($response);
        } catch (RuntimeException $failure) {
            throw new GuardException('Chat response rejected by guard.', $response, $failure);
        }

        return $response;
    }
}
