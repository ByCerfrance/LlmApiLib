<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Fixture;

use ByCerfrance\LlmApiLib\Chat\Streaming\StreamControl;
use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequestInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderStreamEvent;
use ByCerfrance\LlmApiLib\Provider\StreamingChatProviderInterface;
use LogicException;
use Override;
use Throwable;

/**
 * Streaming provider double replaying scripted turns, each a list of events or failures.
 */
class ScriptedStreamingProvider implements StreamingChatProviderInterface
{
    /** @var list<ProviderChatRequestInterface> */
    public array $requests = [];
    /** Number of streamed turns whose resources were released. */
    public int $released = 0;

    /** @param list<list<ProviderStreamEvent|Throwable>> $script */
    public function __construct(private array $script = [])
    {
    }

    /** @return list<ProviderStreamEvent> Text deltas followed by the completed turn. */
    public static function turn(ChatResultInterface $result, string ...$deltas): array
    {
        return [
            ...array_map(static fn(string $text): ProviderStreamEvent => new ProviderStreamEvent('text.delta', $text), $deltas),
            new ProviderStreamEvent('turn.completed', result: $result),
        ];
    }

    #[Override]
    public function getId(): string
    {
        return 'scripted';
    }

    #[Override]
    public function complete(ProviderChatRequestInterface $request): ChatResultInterface
    {
        foreach ($this->stream($request, new StreamControl()) as $event) {
            if ($event->result !== null) {
                return $event->result;
            }
        }

        throw new LogicException('The scripted turn has no result.');
    }

    #[Override]
    public function stream(ProviderChatRequestInterface $request, StreamControl $control): iterable
    {
        $this->requests[] = $request;
        $events = array_shift($this->script) ?? throw new LogicException('No scripted turn left.');
        try {
            foreach ($events as $event) {
                if ($event instanceof Throwable) {
                    throw $event;
                }
                yield $event;
                if ($control->isClosed()) {
                    return;
                }
            }
        } finally {
            $this->released++;
        }
    }
}
