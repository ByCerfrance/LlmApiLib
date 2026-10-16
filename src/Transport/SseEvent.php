<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Transport;

readonly class SseEvent
{
    public function __construct(public string $data, public string $event = 'message', public ?string $id = null, public ?int $retry = null)
    {
    }
}
