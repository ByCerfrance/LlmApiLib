<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

readonly class UsageSnapshot
{
    /** @param list<UsageTotal> $totals */
    public function __construct(public int $recordCount, private array $totals)
    {
    }

    /** @return list<UsageTotal> */
    public function getTotals(): array
    {
        return $this->totals;
    }

    public function get(string $name, string $unit): ?UsageTotal
    {
        foreach ($this->totals as $total) {
            if ($total->name === $name && $total->unit === $unit) {
                return $total;
            }
        }

        return null;
    }
}
