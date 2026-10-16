<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Usage\UsageCollector;
use Override;

readonly class StreamingChatUsage implements StreamingChatInterface
{
    public function __construct(private StreamingChatInterface $chat, private UsageCollector $collector)
    {
    }

    #[Override]
    public function chat(ChatRequestInterface|string $request): ChatResponseInterface
    {
        return (new ChatUsage($this->chat, $this->collector))->chat($request);
    }

    #[Override]
    public function stream(ChatRequestInterface|string $request): ChatStream
    {
        return new ChatStream(function (StreamControl $control) use ($request): iterable {
            $stream = $this->chat->stream($request);
            $closer = $control->onClose($stream->close(...));
            try {
                foreach ($stream as $event) {
                    if ($event->response !== null) {
                        foreach ($event->response->getTurns() as $turn) { $this->collector->addTurn($turn); }
                    }
                    yield $event;
                    if ($control->isClosed()) { return; }
                }
            } finally {
                $stream->close();
                $control->remove($closer);
            }
        });
    }
}
