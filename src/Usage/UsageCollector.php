<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use InvalidArgumentException;
use WeakMap;

/** Mutable ingestion boundary with immutable snapshots and optional retained detail. */
class UsageCollector
{
    private UsageComposite $detail;
    private UsageAccumulator $compact;
    private ?string $boundary = null;
    /** @var WeakMap<ChatResultInterface, bool> */
    private WeakMap $seenTurns;

    public function __construct(private readonly bool $retainDetails = true)
    {
        $this->detail = new UsageComposite();
        $this->compact = new UsageAccumulator();
        $this->seenTurns = new WeakMap();
    }

    public function add(UsageRecord $record, string $boundary = 'provider'): void
    {
        if ($this->boundary !== null && $this->boundary !== $boundary) {
            throw new InvalidArgumentException('Do not mix provider-attempt and chat-response accounting in one collector.');
        }
        $this->boundary = $boundary;
        if ($this->retainDetails) {
            $this->detail = $this->detail->withEntry($record);
        } else {
            $this->compact->add($record);
        }
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
        return $this->retainDetails ? $this->detail->snapshot() : $this->compact->snapshot();
    }

    public function getDetails(): ?UsageComposite
    {
        return $this->retainDetails ? $this->detail : null;
    }
}
