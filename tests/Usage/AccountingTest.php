<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Usage;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\ChatInterface;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Chat\ChatUsage;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolErrorPolicy;
use ByCerfrance\LlmApiLib\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Guard\GuardException;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Observation\ExecutionEvent;
use ByCerfrance\LlmApiLib\Observation\ExecutionObserverInterface;
use ByCerfrance\LlmApiLib\Observation\UsageObserver;
use ByCerfrance\LlmApiLib\Provider\ChatProviderInterface;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\Failure;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\ObservedProvider;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Provider\RetryProvider;
use ByCerfrance\LlmApiLib\Resilience\RetryPolicy;
use ByCerfrance\LlmApiLib\Resilience\RetryRunner;
use ByCerfrance\LlmApiLib\Usage\Cost;
use ByCerfrance\LlmApiLib\Usage\Measurement;
use ByCerfrance\LlmApiLib\Usage\Pricing;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use ByCerfrance\LlmApiLib\Usage\UnitPricing;
use ByCerfrance\LlmApiLib\Usage\UsageAccumulator;
use ByCerfrance\LlmApiLib\Usage\UsageCollector;
use ByCerfrance\LlmApiLib\Usage\UsageComposite;
use ByCerfrance\LlmApiLib\Usage\UsageRecord;
use ByCerfrance\LlmApiLib\Usage\UsageSnapshot;
use ByCerfrance\LlmApiLib\Usage\UsageTotal;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Measurement::class)]
#[CoversClass(UsageRecord::class)]
#[CoversClass(UsageComposite::class)]
#[CoversClass(UsageAccumulator::class)]
#[CoversClass(UsageSnapshot::class)]
#[CoversClass(UsageTotal::class)]
#[CoversClass(UsageCollector::class)]
#[CoversClass(Pricing::class)]
#[CoversClass(UnitPricing::class)]
#[CoversClass(Cost::class)]
#[CoversClass(UsageObserver::class)]
#[CoversClass(ObservedProvider::class)]
#[CoversClass(ChatUsage::class)]
#[UsesClass(ExecutionEvent::class)]
#[UsesClass(TokenUsage::class)]
#[UsesClass(ChatResult::class)]
#[UsesClass(ChatRequest::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(Conversation::class)]
#[UsesClass(AssistantMessage::class)]
#[UsesClass(Message::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(ToolErrorPolicy::class)]
#[UsesClass(GuardException::class)]
#[UsesClass(Model::class)]
#[UsesClass(ProviderChatRequest::class)]
#[UsesClass(ProviderException::class)]
#[UsesClass(Failure::class)]
#[UsesClass(FailureKind::class)]
#[UsesClass(RetryProvider::class)]
#[UsesClass(RetryPolicy::class)]
#[UsesClass(RetryRunner::class)]
class AccountingTest extends TestCase
{
    public function testNestedLeavesDeduplicateAndSnapshotsStayImmutable(): void
    {
        $first = UsageRecord::fromTokens('first', new TokenUsage(10, 5, 15, 0));
        $second = UsageRecord::fromTokens('second', new TokenUsage(20, 10, 30, 2));
        $child = new UsageComposite($first, $second);
        $parent = new UsageComposite($child, $first);
        $snapshot = $parent->snapshot();
        $extended = $parent->withEntry(UsageRecord::fromTokens('third', new TokenUsage(1, 1, 2, 0)));
        $this->assertCount(2, $parent->getEntries());
        $this->assertCount(2, $parent->getRecords());
        $this->assertSame(45, $snapshot->get('total', 'token')->getValue());
        $this->assertSame(47, $extended->snapshot()->get('total', 'token')->getValue());
        $this->assertSame(45, $snapshot->get('total', 'token')->getValue());
    }

    public function testIdentityCollisionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UsageComposite(new UsageRecord('same', [new Measurement('total', 'token', 1)]), new UsageRecord('same', [new Measurement('total', 'token', 2)]));
    }

    public function testUnknownMetricsRetainKnownSubtotalsAndSeparateUnits(): void
    {
        $usage = new UsageComposite(
            new UsageRecord('one', [new Measurement('output', 'token', 12), new Measurement('output', 'image', 2)]),
            new UsageRecord('two', [new Measurement('output', 'token', null)]),
        );
        $total = $usage->snapshot()->get('output', 'token');
        $this->assertNull($total->getValue());
        $this->assertSame(12, $total->knownValue);
        $this->assertSame(1, $total->unknownCount);
        $this->assertSame(2, $usage->snapshot()->get('output', 'image')->getValue());
        $this->assertNull($usage->snapshot()->get('input', 'token'));
    }

    public function testCompactCollectionKeepsOnlyTotalsAndIndependentSnapshots(): void
    {
        $collector = new UsageCollector(retainDetails: false);
        $collector->add(UsageRecord::fromTokens('one', new TokenUsage(1, 2, 3, 0)));
        $snapshot = $collector->snapshot();
        for ($i = 0; $i < 100; $i++) {
            $collector->add(UsageRecord::fromTokens((string) $i, new TokenUsage(1, 2, 3, 0)));
        }
        $this->assertNull($collector->getDetails());
        $this->assertSame(1, $snapshot->recordCount);
        $this->assertSame(3, $snapshot->get('total', 'token')->getValue());
        $this->assertSame(101, $collector->snapshot()->recordCount);
        $this->assertCount(4, $collector->snapshot()->getTotals());
    }

    public function testCacheAwarePricingAndUnknownRates(): void
    {
        $pricing = new Pricing('USD', input: 10, output: 20, cachedInput: 2, perTokens: 1000);
        $this->assertEqualsWithDelta(11.4, $pricing->estimate(new TokenUsage(1000, 150, 1150, 200))->amount, 0.000001);
        $this->assertNull($pricing->estimate(new TokenUsage(1000, 150))->amount);
        $this->assertNull((new Pricing('USD'))->estimate(new TokenUsage(10, 1, 11, 0))->amount);
        $this->assertSame(0.0, (new Pricing('USD'))->estimate(new TokenUsage(0, 0, 0, 0))->amount);
        $equal = new Pricing('EUR', 1, 2, 1, 1000);
        $this->assertSame(1.2, $equal->estimate(new TokenUsage(1000, 100))->amount);
        $this->assertSame($pricing, (new Model('model', pricing: $pricing))->getPricing());
    }

    public function testCurrenciesAreNeverSummedTogether(): void
    {
        $usd = new Cost(1.5, 'USD');
        $eur = new Cost(2.0, 'EUR');
        $snapshot = (new UsageComposite(new UsageRecord('usd', [$usd->toMeasurement()]), new UsageRecord('eur', [$eur->toMeasurement()])))->snapshot();
        $this->assertSame(1.5, $snapshot->get('cost', 'USD')->getValue());
        $this->assertSame(2.0, $snapshot->get('cost', 'EUR')->getValue());
        $this->assertSame(0.08, (new UnitPricing('image', 'USD', 0.04))->estimate(new Measurement('output', 'image', 2))->amount);
        $this->expectException(InvalidArgumentException::class);
        (new UnitPricing('image', 'USD', 0.04))->estimate(new Measurement('output', 'token', 2));
    }

    public function testObservedAttemptsRetainFailureConsumption(): void
    {
        $failure = Failure::fromResponse('provider', new Response('{"usage":{"prompt_tokens":4,"completion_tokens":1,"total_tokens":5}}', 503));
        $provider = $this->createMock(ChatProviderInterface::class);
        $provider->method('getId')->willReturn('provider');
        $attempt = 0;
        $provider->expects($this->exactly(2))->method('complete')->willReturnCallback(function () use (&$attempt, $failure): ChatResult {
            if ($attempt++ === 0) {
                throw $failure;
            }
            return $this->chatResult();
        });
        $collector = new UsageCollector();
        $retry = new RetryProvider(new ObservedProvider($provider, new UsageObserver($collector)), new RetryPolicy(initialDelayMs: 0));
        $retry->complete(new ProviderChatRequest(new Model('model'), new ChatRequest(new Conversation())));
        $this->assertSame(2, $collector->snapshot()->recordCount);
        $this->assertSame(20, $collector->snapshot()->get('total', 'token')->getValue());
        $this->assertNull($collector->snapshot()->get('cached_input', 'token')->getValue());
    }

    public function testUnreportedFailureRemainsUnknownAndEventsCarryNoExceptionText(): void
    {
        $provider = $this->createMock(ChatProviderInterface::class);
        $provider->method('getId')->willReturn('provider');
        $provider->expects($this->once())->method('complete')->willThrowException(new ProviderException('secret', kind: FailureKind::TRANSPORT));
        $events = [];
        $observer = $this->createMock(ExecutionObserverInterface::class);
        $observer->expects($this->exactly(2))->method('observe')->willReturnCallback(static function (ExecutionEvent $event) use (&$events): void { $events[] = $event; });
        try {
            (new ObservedProvider($provider, $observer))->complete(new ProviderChatRequest(new Model('model'), new ChatRequest(new Conversation())));
            $this->fail('Expected failure.');
        } catch (ProviderException) {
            $this->assertSame('provider.failed', $events[1]->phase);
            $this->assertSame('transport', $events[1]->failureKind);
            $this->assertSame($events[0]->executionId, $events[1]->executionId);
            $this->assertNull((new UsageComposite($events[1]->usage))->snapshot()->get('total', 'token')->getValue());
            $this->assertStringNotContainsString('secret', json_encode($events));
        }
    }

    public function testNestedChatTrackersDoNotCountTheSameTurnTwice(): void
    {
        $response = new ChatResponse(new Conversation(), $this->chatResult());
        $chat = $this->createMock(ChatInterface::class);
        $chat->expects($this->once())->method('chat')->willReturn($response);
        $collector = new UsageCollector();
        (new ChatUsage(new ChatUsage($chat, $collector), $collector))->chat('Hi');
        $this->assertSame(1, $collector->snapshot()->recordCount);
        $this->assertSame(15, $collector->snapshot()->get('total', 'token')->getValue());
    }

    public function testRejectedGuardResponseIsStillCounted(): void
    {
        $response = new ChatResponse(new Conversation(), $this->chatResult());
        $chat = $this->createMock(ChatInterface::class);
        $chat->expects($this->once())->method('chat')->willThrowException(new GuardException('rejected', $response));
        $collector = new UsageCollector();
        try {
            (new ChatUsage($chat, $collector))->chat('Hi');
            $this->fail('Expected rejection.');
        } catch (GuardException) {
            $this->assertSame(15, $collector->snapshot()->get('total', 'token')->getValue());
        }
    }

    public function testAccountingBoundariesCannotBeMixed(): void
    {
        $collector = new UsageCollector();
        $collector->addTurn($this->chatResult());
        $this->expectException(InvalidArgumentException::class);
        $collector->add(UsageRecord::fromTokens('attempt', new TokenUsage()));
    }

    private function chatResult(): ChatResult
    {
        return new ChatResult(new AssistantMessage('Done'), new TokenUsage(10, 5, 15, 0), null, 'provider', 'model');
    }
}
