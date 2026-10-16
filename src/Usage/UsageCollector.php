<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use InvalidArgumentException;
use WeakMap;

/** Mutable ingestion boundary with immutable snapshots and optional retained detail. */
class UsageCollector
{
    /** @var list<UsageRecord> Ingested records, in order. */
    private array $entries = [];
    /** @var array<string, UsageRecord> Distinct records by identity. */
    private array $records = [];
    private UsageAccumulator $compact;
    private ?string $boundary = null;
    /** @var WeakMap<ChatResultInterface, bool> */
    private WeakMap $seenTurns;

    public function __construct(private readonly bool $retainDetails = true)
    {
        $this->compact = new UsageAccumulator();
        $this->seenTurns = new WeakMap();
    }

    public function add(UsageRecord $record, string $boundary = 'provider'): void
    {
        if ($this->boundary !== null && $this->boundary !== $boundary) {
            throw new InvalidArgumentException('Do not mix provider-attempt and chat-response accounting in one collector.');
        }
        $this->boundary = $boundary;
        if (!$this->retainDetails) {
            $this->compact->add($record);

            return;
        }
        $known = $this->records[$record->id] ?? null;
        if ($known !== null && $known != $record) {
            throw new InvalidArgumentException('A usage identity cannot describe different consumption.');
        }
        $this->records[$record->id] = $record;
        $this->entries[] = $record;
    }

    public function addTurn(ChatResultInterface $turn): void
    {
        if (isset($this->seenTurns[$turn])) {
            return;
        }
        $this->add(UsageRecord::fromTokens(bin2hex(random_bytes(16)), $turn->getUsage(), $turn->getProviderId(), $turn->getModelName()), 'chat');
        $this->seenTurns[$turn] = true;
    }

    public function snapshot(): UsageSnapshot
    {
        if (!$this->retainDetails) {
            return $this->compact->snapshot();
        }
        $accumulator = new UsageAccumulator();
        foreach ($this->records as $record) {
            $accumulator->add($record);
        }

        return $accumulator->snapshot();
    }

    public function getDetails(): ?UsageComposite
    {
        return $this->retainDetails ? new UsageComposite(...$this->entries) : null;
    }
}
