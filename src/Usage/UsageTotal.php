<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

readonly class UsageTotal
{
    public function __construct(
        public string $name,
        public string $unit,
        public int|float $knownValue = 0,
        public int $unknownCount = 0,
    ) {
    }

    public function getValue(): int|float|null
    {
        return $this->unknownCount === 0 ? $this->knownValue : null;
    }
}
