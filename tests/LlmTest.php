<?php

namespace ByCerfrance\LlmApiLib\Tests;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionResponse;
use ByCerfrance\LlmApiLib\Completion\Content\InputAudioContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\Message\UserMessage;
use ByCerfrance\LlmApiLib\Llm;
use ByCerfrance\LlmApiLib\LlmInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\CostTier;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Model\QualityTier;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Provider\AbstractProvider;
use ByCerfrance\LlmApiLib\Provider\Generic;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Usage\Usage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(Llm::class)]
#[UsesClass(Completion::class)]
#[UsesClass(CompletionResponse::class)]
#[UsesClass(InputAudioContent::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(Capability::class)]
#[UsesClass(CostTier::class)]
#[UsesClass(ModelInfo::class)]
#[UsesClass(QualityTier::class)]
#[UsesClass(SelectionStrategy::class)]
#[UsesClass(Generic::class)]
#[UsesClass(AbstractProvider::class)]
#[UsesClass(ProviderException::class)]
#[UsesClass(Usage::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(UserMessage::class)]
class LlmTest extends TestCase
{
    public function testGetProviders(): void
    {
        $firstLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('foo', capabilities: [Capability::DOCUMENT, Capability::IMAGE]),
            client: $this->createMock(ClientInterface::class)
        );
        $secondLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('bar', capabilities: [Capability::AUDIO]),
            client: $this->createMock(ClientInterface::class)
        );
        $llm = new Llm($firstLlm, $secondLlm);

        $providers = $llm->getProviders();
        $this->assertEquals(
            [$firstLlm, $secondLlm],
            $providers,
        );
    }

    public function testFilterByCapabilities(): void
    {
        $firstLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('foo', capabilities: [Capability::DOCUMENT, Capability::IMAGE]),
            client: $this->createMock(ClientInterface::class)
        );
        $secondLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('bar', capabilities: [Capability::AUDIO]),
            client: $this->createMock(ClientInterface::class)
        );
        $llm = new Llm($firstLlm, $secondLlm);

        $filtered = $llm->filterByCapabilities(Capability::AUDIO);
        $this->assertEquals(
            [$secondLlm],
            $filtered->getProviders(),
        );

        $filtered = $llm->filterByCapabilities(Capability::DOCUMENT, Capability::IMAGE);
        $this->assertEquals(
            [$firstLlm],
            $filtered->getProviders(),
        );
    }

    public function testFilterByCapabilitiesEmpty(): void
    {
        $llm = new Llm($this->createMock(LlmInterface::class));
        $this->assertSame($llm, $llm->filterByCapabilities());
    }

    public function testFilterByCapabilitiesThrowsWhenNoMatch(): void
    {
        $this->expectException(RuntimeException::class);

        $firstLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('foo', capabilities: [Capability::TEXT]),
            client: $this->createMock(ClientInterface::class)
        );
        $llm = new Llm($firstLlm);
        $llm->filterByCapabilities(Capability::AUDIO);
    }

    public function testSortByStrategy(): void
    {
        $firstLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('foo'),
            client: $this->createMock(ClientInterface::class)
        );
        $secondLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('bar', qualityTier: QualityTier::PREMIUM, costTier: CostTier::HIGH),
            client: $this->createMock(ClientInterface::class)
        );
        $llm = new Llm($firstLlm, $secondLlm);

        $sorted = $llm->sortByStrategy(SelectionStrategy::BEST_QUALITY);
        $this->assertSame(
            [$secondLlm, $firstLlm],
            $sorted->getProviders(),
        );

        $sorted = $llm->sortByStrategy(SelectionStrategy::BALANCED);
        $this->assertSame(
            [$firstLlm, $secondLlm],
            $sorted->getProviders(),
        );
    }

    public function testSortByStrategyNull(): void
    {
        $llm = new Llm($this->createMock(LlmInterface::class));
        $this->assertSame($llm, $llm->sortByStrategy(null));
    }

    public function testFilterByLabels(): void
    {
        $firstLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('foo'),
            client: $this->createMock(ClientInterface::class),
            labels: ['summarize', 'cheap-tasks'],
        );
        $secondLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('bar'),
            client: $this->createMock(ClientInterface::class),
            labels: ['classification'],
        );
        $thirdLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('baz'),
            client: $this->createMock(ClientInterface::class),
            labels: ['summarize', 'classification'],
        );
        $llm = new Llm($firstLlm, $secondLlm, $thirdLlm);

        // AND: provider must have ALL labels
        $filtered = $llm->filterByLabels(['summarize']);
        $this->assertEquals([$firstLlm, $thirdLlm], $filtered->getProviders());

        $filtered = $llm->filterByLabels(['summarize', 'classification']);
        $this->assertEquals([$thirdLlm], $filtered->getProviders());

        $filtered = $llm->filterByLabels(['classification']);
        $this->assertEquals([$secondLlm, $thirdLlm], $filtered->getProviders());
    }

    public function testFilterByLabelsMatchAny(): void
    {
        $firstLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('foo'),
            client: $this->createMock(ClientInterface::class),
            labels: ['summarize'],
        );
        $secondLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('bar'),
            client: $this->createMock(ClientInterface::class),
            labels: ['classification'],
        );
        $thirdLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('baz'),
            client: $this->createMock(ClientInterface::class),
            labels: ['translation'],
        );
        $llm = new Llm($firstLlm, $secondLlm, $thirdLlm);

        // OR: provider must have ANY of the labels
        $filtered = $llm->filterByLabels(['summarize', 'classification'], matchAll: false);
        $this->assertEquals([$firstLlm, $secondLlm], $filtered->getProviders());
    }

    public function testFilterByLabelsEmpty(): void
    {
        $llm = new Llm($this->createMock(LlmInterface::class));
        $this->assertSame($llm, $llm->filterByLabels([]));
    }

    public function testFilterByLabelsThrowsWhenNoMatch(): void
    {
        $this->expectException(RuntimeException::class);

        $firstLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('foo'),
            client: $this->createMock(ClientInterface::class),
            labels: ['summarize'],
        );
        $llm = new Llm($firstLlm);
        $llm->filterByLabels(['classification']);
    }

    public function testGetLabels(): void
    {
        $firstLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('foo'),
            client: $this->createMock(ClientInterface::class),
            labels: ['summarize', 'cheap-tasks'],
        );
        $secondLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('bar'),
            client: $this->createMock(ClientInterface::class),
            labels: ['classification', 'summarize'],
        );
        $llm = new Llm($firstLlm, $secondLlm);

        $labels = $llm->getLabels();
        sort($labels);
        $this->assertSame(['cheap-tasks', 'classification', 'summarize'], $labels);
    }

    public function testGetLabelsEmpty(): void
    {
        $firstLlm = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('foo'),
            client: $this->createMock(ClientInterface::class),
        );
        $llm = new Llm($firstLlm);

        $this->assertSame([], $llm->getLabels());
    }

    public function testCount(): void
    {
        $llm = new Llm(
            $this->createMock(LlmInterface::class),
            $this->createMock(LlmInterface::class),
            $this->createMock(LlmInterface::class),
        );

        $this->assertCount(3, $llm);
    }

    public function testIterator(): void
    {
        $first = $this->createMock(LlmInterface::class);
        $second = $this->createMock(LlmInterface::class);
        $llm = new Llm($first, $second);

        $iterated = iterator_to_array($llm);
        $this->assertSame([$first, $second], $iterated);
    }

    public function testChatWithLabels(): void
    {
        $firstProvider = $this->createMock(LlmInterface::class);
        $firstProvider->method('supports')->willReturn(true);
        $firstProvider->method('getLabels')->willReturn(['summarize']);
        $firstProvider->method('getScoring')->willReturn(1.0);
        $firstProvider
            ->method('chat')
            ->willReturn(
                $expected = new CompletionResponse(
                    new Completion([]),
                    new Usage()
                )
            );

        $secondProvider = $this->createMock(LlmInterface::class);
        $secondProvider->method('supports')->willReturn(true);
        $secondProvider->method('getLabels')->willReturn(['classification']);
        $secondProvider->method('getScoring')->willReturn(1.0);
        $secondProvider
            ->expects($this->never())
            ->method('chat');

        $llm = new Llm($firstProvider, $secondProvider);
        $result = $llm->chat(
            (new Completion([]))->withLabels(['summarize']),
        );

        $this->assertSame($expected, $result);
    }

    public function testGetUsage(): void
    {
        $firstLlm = $this->createMock(LlmInterface::class);
        $firstLlm->method('getUsage')->willReturn(new Usage(promptTokens: 10, completionTokens: 20, totalTokens: 30));
        $secondLlm = $this->createMock(LlmInterface::class);
        $secondLlm->method('getUsage')->willReturn(new Usage(promptTokens: 15, completionTokens: 25, totalTokens: 40));

        $llm = new Llm($firstLlm, $secondLlm);

        $this->assertEquals(
            new Usage(promptTokens: 25, completionTokens: 45, totalTokens: 70),
            $llm->getUsage(),
        );
    }

    public function testGetCost(): void
    {
        $firstLlm = $this->createMock(LlmInterface::class);
        $firstLlm->method('getCost')->willReturn(10.0);
        $secondLlm = $this->createMock(LlmInterface::class);
        $secondLlm->method('getCost')->willReturn(20.0);

        $llm = new Llm($firstLlm, $secondLlm);

        $this->assertEquals(30, $llm->getCost());
    }

    public function testGetCapabilities(): void
    {
        $firstLlm = $this->createMock(LlmInterface::class);
        $secondLlm = $this->createMock(LlmInterface::class);

        $firstLlm->method('getCapabilities')->willReturn([Capability::DOCUMENT]);
        $secondLlm->method('getCapabilities')->willReturn([Capability::AUDIO]);

        $llm = new Llm($firstLlm, $secondLlm);

        $this->assertEquals(
            [Capability::DOCUMENT, Capability::AUDIO],
            iterator_to_array($llm->getCapabilities()),
        );
    }

    public function testSupports(): void
    {
        $firstLlm = $this->createMock(LlmInterface::class);
        $secondLlm = $this->createMock(LlmInterface::class);

        $firstLlm
            ->method('supports')
            ->willReturnCallback(fn(Capability ...$capability) => empty(
            array_udiff(
                $capability,
                [Capability::DOCUMENT, Capability::IMAGE],
                fn(Capability $a, Capability $b) => strcmp($a->name, $b->name),
            )
            ));
        $secondLlm
            ->method('supports')
            ->willReturnCallback(fn(Capability ...$capability) => empty(
            array_udiff(
                $capability,
                [Capability::AUDIO],
                fn(Capability $a, Capability $b) => strcmp($a->name, $b->name),
            )
            ));

        $llm = new Llm($firstLlm, $secondLlm);

        $this->assertTrue($llm->supports(Capability::DOCUMENT));
        $this->assertTrue($llm->supports(Capability::AUDIO));
        $this->assertTrue($llm->supports(Capability::DOCUMENT, Capability::IMAGE));
        $this->assertFalse($llm->supports(Capability::DOCUMENT, Capability::AUDIO));
        $this->assertFalse($llm->supports(Capability::VIDEO));
    }

    public function testChatWithoutCapabilities(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'No LLM provider compatible with the given completion'
        );
        $llm = new Llm($fakeLlm = $this->createStub(LlmInterface::class));
        $fakeLlm->method('supports')->willReturn(false);

        $llm->chat('Hello world!');
    }

    public function testChatLogsProviderFailover(): void
    {
        $firstProvider = $this->createMock(LlmInterface::class);
        $firstProvider->method('supports')->willReturn(true);
        $firstProvider->method('getLabels')->willReturn([]);
        $firstProvider->method('getScoring')->willReturn(1.0);
        $firstProvider->method('getId')->willReturn('provider-1');
        $firstProvider
            ->method('chat')
            ->willThrowException(new RuntimeException('Provider 1 failed'));

        $secondProvider = $this->createMock(LlmInterface::class);
        $secondProvider->method('supports')->willReturn(true);
        $secondProvider->method('getLabels')->willReturn([]);
        $secondProvider->method('getScoring')->willReturn(1.0);
        $secondProvider->method('getId')->willReturn('provider-2');
        $secondProvider
            ->method('chat')
            ->willReturn(
                $expected = new CompletionResponse(
                    new Completion([]),
                    new Usage()
                )
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'LLM provider {provider} failed, trying next',
                $this->callback(fn(array $context) => $context['provider'] === 'provider-1' &&
                    $context['exception'] === 'Provider 1 failed'
                )
            );

        $llm = new Llm($firstProvider, $secondProvider);
        $result = $llm->chat(new Completion([]), $logger);

        $this->assertSame($expected, $result);
    }

    public function testChatLogsResponseBodyOnProviderExceptionFailover(): void
    {
        $firstProvider = $this->createMock(LlmInterface::class);
        $firstProvider->method('supports')->willReturn(true);
        $firstProvider->method('getLabels')->willReturn([]);
        $firstProvider->method('getScoring')->willReturn(1.0);
        $firstProvider
            ->method('chat')
            ->willThrowException(new ProviderException('Provider 1 failed', '{"error":"quota exceeded"}'));

        $secondProvider = $this->createMock(LlmInterface::class);
        $secondProvider->method('supports')->willReturn(true);
        $secondProvider->method('getLabels')->willReturn([]);
        $secondProvider->method('getScoring')->willReturn(1.0);
        $secondProvider
            ->method('chat')
            ->willReturn(
                $expected = new CompletionResponse(
                    new Completion([]),
                    new Usage()
                )
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'LLM provider {provider} failed, trying next',
                $this->callback(fn(array $context) => $context['exception'] === 'Provider 1 failed' &&
                    $context['response_body'] === '{"error":"quota exceeded"}'
                )
            );

        $llm = new Llm($firstProvider, $secondProvider);
        $result = $llm->chat(new Completion([]), $logger);

        $this->assertSame($expected, $result);
    }

    public function testGetId(): void
    {
        $llm = new Llm($this->createMock(LlmInterface::class));

        $this->assertSame('Llm', $llm->getId());
    }

    public function testGetIdForGenericProvider(): void
    {
        $provider = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('gpt-4o'),
            client: $this->createMock(ClientInterface::class),
        );

        $this->assertSame('Generic.gpt-4o', $provider->getId());
    }

    public function testGetIdForGenericProviderWithCustomId(): void
    {
        $provider = new Generic(
            uri: 'http://localhost',
            apiKey: '',
            model: new ModelInfo('gpt-4o'),
            client: $this->createMock(ClientInterface::class),
            id: 'my-openai',
        );

        $this->assertSame('my-openai', $provider->getId());
    }

    public function testGetMaxContextTokens(): void
    {
        $firstLlm = $this->createMock(LlmInterface::class);
        $firstLlm->method('getMaxContextTokens')->willReturn(128000);
        $secondLlm = $this->createMock(LlmInterface::class);
        $secondLlm->method('getMaxContextTokens')->willReturn(200000);

        $llm = new Llm($firstLlm, $secondLlm);

        $this->assertSame(128000, $llm->getMaxContextTokens());
    }

    public function testGetMaxContextTokensAllNull(): void
    {
        $firstLlm = $this->createMock(LlmInterface::class);
        $firstLlm->method('getMaxContextTokens')->willReturn(null);
        $secondLlm = $this->createMock(LlmInterface::class);
        $secondLlm->method('getMaxContextTokens')->willReturn(null);

        $llm = new Llm($firstLlm, $secondLlm);

        $this->assertNull($llm->getMaxContextTokens());
    }

    public function testGetMaxContextTokensMixed(): void
    {
        $firstLlm = $this->createMock(LlmInterface::class);
        $firstLlm->method('getMaxContextTokens')->willReturn(null);
        $secondLlm = $this->createMock(LlmInterface::class);
        $secondLlm->method('getMaxContextTokens')->willReturn(64000);
        $thirdLlm = $this->createMock(LlmInterface::class);
        $thirdLlm->method('getMaxContextTokens')->willReturn(128000);

        $llm = new Llm($firstLlm, $secondLlm, $thirdLlm);

        $this->assertSame(64000, $llm->getMaxContextTokens());
    }

    public function testGetMaxOutputTokens(): void
    {
        $firstLlm = $this->createMock(LlmInterface::class);
        $firstLlm->method('getMaxOutputTokens')->willReturn(16384);
        $secondLlm = $this->createMock(LlmInterface::class);
        $secondLlm->method('getMaxOutputTokens')->willReturn(8192);

        $llm = new Llm($firstLlm, $secondLlm);

        $this->assertSame(8192, $llm->getMaxOutputTokens());
    }

    public function testGetMaxOutputTokensAllNull(): void
    {
        $firstLlm = $this->createMock(LlmInterface::class);
        $firstLlm->method('getMaxOutputTokens')->willReturn(null);
        $secondLlm = $this->createMock(LlmInterface::class);
        $secondLlm->method('getMaxOutputTokens')->willReturn(null);

        $llm = new Llm($firstLlm, $secondLlm);

        $this->assertNull($llm->getMaxOutputTokens());
    }

    public function testGetMaxOutputTokensMixed(): void
    {
        $firstLlm = $this->createMock(LlmInterface::class);
        $firstLlm->method('getMaxOutputTokens')->willReturn(null);
        $secondLlm = $this->createMock(LlmInterface::class);
        $secondLlm->method('getMaxOutputTokens')->willReturn(8192);
        $thirdLlm = $this->createMock(LlmInterface::class);
        $thirdLlm->method('getMaxOutputTokens')->willReturn(16384);

        $llm = new Llm($firstLlm, $secondLlm, $thirdLlm);

        $this->assertSame(8192, $llm->getMaxOutputTokens());
    }

    public function testChatLogsAllProvidersFailure(): void
    {
        $provider = $this->createMock(LlmInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('getLabels')->willReturn([]);
        $provider->method('getScoring')->willReturn(1.0);
        $provider
            ->method('chat')
            ->willThrowException(new ProviderException('Provider failed', '{"error":"invalid api key"}'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning');
        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'All LLM providers failed',
                $this->isArray()
            );

        $llm = new Llm($provider);

        try {
            $llm->chat(new Completion([]), $logger);
            self::fail('ProviderException was not thrown');
        } catch (ProviderException $exception) {
            self::assertSame('{"error":"invalid api key"}', $exception->getBody());
        }
    }
}
