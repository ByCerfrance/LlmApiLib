<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

readonly class ProviderStreamEvent
{
    public function __construct(public string $type, public ?string $text = null, public ?int $toolIndex = null, public ?ChatResultInterface $result = null)
    {
    }
}
