<?php

namespace ByCerfrance\LlmApiLib\Tests;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionResponse;
use ByCerfrance\LlmApiLib\Completion\Content\InputAudioContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Llm;
use ByCerfrance\LlmApiLib\LlmInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\CostTier;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Model\QualityTier;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Provider\AbstractProvider;
use ByCerfrance\LlmApiLib\Provider\Generic;
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
#[UsesClass(Usage::class)]
#[UsesClass(TextContent::class)]
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
            iterator_to_array($providers),
        );

        $providers = $llm->getProviders(
            new Completion(
                messages: [
                    new Message(content: new InputAudioContent('', ''), role: RoleEnum::USER),
                ]
            )
        );
        $this->assertEquals(
            [$secondLlm],
            iterator_to_array($providers),
        );
    }

    public function testGetProvidersWithStrategy(): void
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

        $providers = $llm->getProviders(new Completion(messages: [], selectionStrategy: SelectionStrategy::BEST_QUALITY)
        );
        $this->assertSame(
            [$secondLlm, $firstLlm],
            iterator_to_array($providers),
        );

        $providers = $llm->getProviders(new Completion(messages: [], selectionStrategy: SelectionStrategy::BALANCED));
        $this->assertSame(
            [$firstLlm, $secondLlm],
            iterator_to_array($providers),
        );
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
            'No LLM provider compatible with the given completion (required capabilities: text)'
        );
        $llm = new Llm($fakeLlm = $this->createStub(LlmInterface::class));
        $fakeLlm->method('supports')->willReturn(false);

        $llm->chat('Hello world!');
    }

    public function testChatLogsProviderFailover(): void
    {
        $firstProvider = $this->createMock(LlmInterface::class);
        $firstProvider->method('supports')->willReturn(true);
        $firstProvider
            ->method('chat')
            ->willThrowException(new RuntimeException('Provider 1 failed'));

        $secondProvider = $this->createMock(LlmInterface::class);
        $secondProvider->method('supports')->willReturn(true);
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
                $this->callback(fn(array $context) => str_contains($context['provider'], 'Mock') &&
                    $context['exception'] === 'Provider 1 failed'
                )
            );

        $llm = new Llm($firstProvider, $secondProvider);
        $result = $llm->chat(new Completion([]), $logger);

        $this->assertSame($expected, $result);
    }

    public function testChatLogsAllProvidersFailure(): void
    {
        $provider = $this->createMock(LlmInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider
            ->method('chat')
            ->willThrowException(new RuntimeException('Provider failed'));

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

        $this->expectException(RuntimeException::class);
        $llm->chat(new Completion([]), $logger);
    }
}
