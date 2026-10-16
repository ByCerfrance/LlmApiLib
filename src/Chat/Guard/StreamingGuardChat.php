<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat\Guard;

use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;
use ByCerfrance\LlmApiLib\Chat\ChatResponseInterface;
use ByCerfrance\LlmApiLib\Chat\Streaming\ChatStream;
use ByCerfrance\LlmApiLib\Chat\Streaming\StreamControl;
use ByCerfrance\LlmApiLib\Chat\Streaming\StreamingChatInterface;
use Closure;
use Override;
use RuntimeException;

readonly class StreamingGuardChat implements StreamingChatInterface
{
    /** @param Closure(ChatResponseInterface): void $guard */
    public function __construct(private StreamingChatInterface $chat, private Closure $guard)
    {
    }

    #[Override]
    public function chat(ChatRequestInterface|string $request): ChatResponseInterface
    {
        return (new GuardChat($this->chat, $this->guard))->chat($request);
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
                        try { ($this->guard)($event->response); }
                        catch (RuntimeException $failure) { throw new GuardException('Streamed response rejected by guard.', $event->response, $failure); }
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
