<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

use InvalidArgumentException;

readonly class UsageComposite
{
    /** @var list<UsageRecord|UsageComposite> */
    private array $entries;

    public function __construct(UsageRecord|UsageComposite ...$entries)
    {
        $this->entries = array_values($entries);
        $this->getRecords();
    }

    /** @return list<UsageRecord|UsageComposite> Original nested entries. */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /** @return list<UsageRecord> Deduplicated leaves; aggregate totals are never counted as records. */
    public function getRecords(): array
    {
        $records = [];
        foreach ($this->entries as $entry) {
            foreach ($entry instanceof UsageRecord ? [$entry] : $entry->getRecords() as $record) {
                if (isset($records[$record->id]) && $records[$record->id] != $record) {
                    throw new InvalidArgumentException('A usage identity cannot describe different consumption.');
                }
                $records[$record->id] = $record;
            }
        }

        return array_values($records);
    }

    public function withEntry(UsageRecord|self $entry): self
    {
        return new self(...[...$this->entries, $entry]);
    }

    public function snapshot(): UsageSnapshot
    {
        $accumulator = new UsageAccumulator();
        foreach ($this->getRecords() as $record) {
            $accumulator->add($record);
        }

        return $accumulator->snapshot();
    }
}
