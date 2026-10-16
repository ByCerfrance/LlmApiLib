<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Transport;

use RuntimeException;

/** Incremental SSE framing, independent of network chunk and UTF-8 boundaries. */
class SseDecoder
{
    private string $buffer = '';
    private array $data = [];
    private string $event = '';
    private ?string $id = null;
    private ?int $retry = null;
    private bool $first = true;
    private int $size = 0;

    public function __construct(private readonly int $maxEventBytes = 16777216)
    {
    }

    /** @return list<SseEvent> */
    public function feed(string $chunk): array
    {
        $this->buffer .= $chunk;
        if ($this->first) {
            if (strlen($this->buffer) < 3) { return []; }
            $this->first = false;
            if (str_starts_with($this->buffer, "\xEF\xBB\xBF")) { $this->buffer = substr($this->buffer, 3); }
        }
        $events = [];
        while (preg_match('/\r\n|\r|\n/', $this->buffer, $match, PREG_OFFSET_CAPTURE)) {
            $separator = $match[0][0];
            $offset = $match[0][1];
            if ($separator === "\r" && $offset === strlen($this->buffer) - 1) { break; }
            $line = substr($this->buffer, 0, $offset);
            $this->buffer = substr($this->buffer, $offset + strlen($separator));
            $this->size += strlen($line);
            if ($this->size > $this->maxEventBytes) { throw new RuntimeException('SSE event exceeds configured size limit.'); }
            if ($line === '') {
                if ($this->data !== []) {
                    $events[] = new SseEvent(implode("\n", $this->data), $this->event ?: 'message', $this->id, $this->retry);
                }
                $this->data = [];
                $this->event = '';
                $this->size = 0;
                continue;
            }
            if ($line[0] === ':') { continue; }
            [$field, $value] = array_pad(explode(':', $line, 2), 2, '');
            if (str_starts_with($value, ' ')) { $value = substr($value, 1); }
            match ($field) {
                'data' => $this->data[] = $value,
                'event' => $this->event = $value,
                'id' => !str_contains($value, "\0") ? $this->id = $value : null,
                'retry' => ctype_digit($value) ? $this->retry = (int) $value : null,
                default => null,
            };
        }
        if ($this->size + strlen($this->buffer) > $this->maxEventBytes) { throw new RuntimeException('SSE event exceeds configured size limit.'); }
        return $events;
    }

    /** @param iterable<string> $chunks @return iterable<SseEvent> */
    public function events(iterable $chunks): iterable
    {
        foreach ($chunks as $chunk) {
            yield from $this->feed($chunk);
        }
        if (str_ends_with($this->buffer, "\r")) {
            yield from $this->feed("\n");
        }
    }
}
