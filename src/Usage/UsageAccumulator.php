<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

/** Compact totals without history or ID retention. Feed each record exactly once. */
class UsageAccumulator
{
    /** @var array<string, UsageTotal> */
    private array $totals = [];
    private int $recordCount = 0;

    public function add(UsageRecord $record): void
    {
        foreach ($record->getMeasurements() as $measurement) {
            $key = json_encode([$measurement->name, $measurement->unit], JSON_THROW_ON_ERROR);
            $previous = $this->totals[$key] ?? new UsageTotal($measurement->name, $measurement->unit);
            $this->totals[$key] = new UsageTotal($measurement->name, $measurement->unit,
                $previous->knownValue + ($measurement->value ?? 0),
                $previous->unknownCount + ($measurement->value === null ? 1 : 0));
        }
        $this->recordCount++;
    }

    public function snapshot(): UsageSnapshot
    {
        return new UsageSnapshot($this->recordCount, array_values($this->totals));
    }
}
