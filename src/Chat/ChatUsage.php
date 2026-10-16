<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Chat\Guard\GuardException;
use ByCerfrance\LlmApiLib\Usage\UsageCollector;
use Override;

readonly class ChatUsage implements ChatInterface
{
    public function __construct(private ChatInterface $chat, private UsageCollector $collector)
    {
    }

    #[Override]
    public function chat(ChatRequestInterface|string $request): ChatResponseInterface
    {
        try {
            $response = $this->chat->chat($request);
        } catch (GuardException $failure) {
            $this->record($failure->getResponse());
            throw $failure;
        }
        $this->record($response);

        return $response;
    }

    private function record(ChatResponseInterface $response): void
    {
        foreach ($response->getTurns() as $turn) {
            $this->collector->addTurn($turn);
        }
    }
}
