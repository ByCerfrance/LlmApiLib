<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Payload\Builder;

use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\CostTier;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Model\QualityTier;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\Builder\TuningStripBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(TuningStripBuilder::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(Capability::class)]
#[UsesClass(CostTier::class)]
#[UsesClass(ModelInfo::class)]
#[UsesClass(QualityTier::class)]
class TuningStripBuilderTest extends TestCase
{
    public function testSupportsArrayWhenModelIsPresent(): void
    {
        $builder = new TuningStripBuilder();
        $context = new BuildContext(model: new ModelInfo(name: 'foo'));

        $this->assertTrue($builder->supports([], $context));
    }

    public function testDoesNotSupportArrayWhenModelIsAbsent(): void
    {
        $builder = new TuningStripBuilder();
        $context = new BuildContext();

        $this->assertFalse($builder->supports([], $context));
    }

    public function testDoesNotSupportNonArrayValues(): void
    {
        $builder = new TuningStripBuilder();
        $context = new BuildContext(model: new ModelInfo(name: 'foo'));

        $this->assertFalse($builder->supports('hello', $context));
        $this->assertFalse($builder->supports(42, $context));
        $this->assertFalse($builder->supports(null, $context));
        $this->assertFalse($builder->supports(new \stdClass(), $context));
    }

    public function testTunableModelDoesNotStripAnything(): void
    {
        $builder = new TuningStripBuilder();
        $context = new BuildContext(model: new ModelInfo(name: 'foo', tunable: true));

        $payload = [
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'temperature' => 0.7,
            'top_p' => 0.9,
            'seed' => 42,
        ];

        $result = $builder->build($payload, $context);

        $this->assertSame($payload, $result);
    }

    public function testNonTunableModelStripsAllTuningParameters(): void
    {
        $builder = new TuningStripBuilder();
        $context = new BuildContext(model: new ModelInfo(name: 'gpt-5', tunable: false));

        $payload = [
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'temperature' => 0.7,
            'top_p' => 0.9,
            'n' => 1,
            'logprobs' => true,
            'top_logprobs' => 3,
            'presence_penalty' => 0.5,
            'frequency_penalty' => 0.5,
            'seed' => 42,
            'max_completion_tokens' => 1000,
        ];

        $result = $builder->build($payload, $context);

        foreach (ModelInfo::TUNING_PARAMETERS as $field) {
            $this->assertArrayNotHasKey($field, $result, sprintf('Field "%s" should be stripped', $field));
        }
        $this->assertArrayHasKey('messages', $result);
        $this->assertArrayHasKey('max_completion_tokens', $result);
    }

    public function testStripFieldsAreRemovedRegardlessOfTunable(): void
    {
        $builder = new TuningStripBuilder();
        $context = new BuildContext(
            model: new ModelInfo(
                name: 'foo',
                tunable: true,
                stripFields: ['service_tier'],
            ),
        );

        $payload = [
            'messages' => [],
            'service_tier' => 'auto',
            'temperature' => 0.7,
        ];

        $result = $builder->build($payload, $context);

        $this->assertArrayNotHasKey('service_tier', $result);
        // temperature is preserved because the model is tunable.
        $this->assertArrayHasKey('temperature', $result);
    }

    public function testStripFieldsAndTunableFalseAreCombined(): void
    {
        $builder = new TuningStripBuilder();
        $context = new BuildContext(
            model: new ModelInfo(
                name: 'gpt-5-azure',
                tunable: false,
                stripFields: ['service_tier'],
            ),
        );

        $payload = [
            'messages' => [],
            'service_tier' => 'auto',
            'temperature' => 0.7,
            'top_p' => 0.9,
        ];

        $result = $builder->build($payload, $context);

        $this->assertArrayNotHasKey('service_tier', $result);
        $this->assertArrayNotHasKey('temperature', $result);
        $this->assertArrayNotHasKey('top_p', $result);
        $this->assertArrayHasKey('messages', $result);
    }

    public function testDeepPathIsStrippedViaDotNotation(): void
    {
        $builder = new TuningStripBuilder();
        $context = new BuildContext(
            model: new ModelInfo(
                name: 'foo',
                stripFields: ['response_format.strict'],
            ),
        );

        $payload = [
            'response_format' => [
                'type' => 'json_schema',
                'strict' => true,
                'schema' => ['type' => 'object'],
            ],
        ];

        $result = $builder->build($payload, $context);

        $this->assertArrayHasKey('response_format', $result);
        $this->assertArrayNotHasKey('strict', $result['response_format']);
        $this->assertArrayHasKey('type', $result['response_format']);
        $this->assertArrayHasKey('schema', $result['response_format']);
    }

    public function testNonExistentPathIsSilentlyIgnored(): void
    {
        $builder = new TuningStripBuilder();
        $context = new BuildContext(
            model: new ModelInfo(
                name: 'foo',
                stripFields: ['does_not_exist'],
            ),
        );

        $payload = ['messages' => []];

        $result = $builder->build($payload, $context);

        $this->assertSame($payload, $result);
    }

    public function testLoggerEmitsWarningForEachStrippedField(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('warning')
            ->with(
                $this->stringContains('stripped'),
                $this->callback(
                    fn($context) =>
                    isset($context['path'], $context['model'])
                    && in_array($context['path'], ['temperature', 'top_p'], true)
                    && $context['model'] === 'gpt-5',
                ),
            );

        $builder = new TuningStripBuilder($logger);
        $context = new BuildContext(model: new ModelInfo(name: 'gpt-5', tunable: false));

        $payload = [
            'temperature' => 0.7,
            'top_p' => 0.9,
            // 'seed' is in TUNING_PARAMETERS but absent from payload → no warning emitted.
        ];

        $builder->build($payload, $context);
    }

    public function testLoggerNotCalledWhenNothingIsStripped(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $builder = new TuningStripBuilder($logger);
        $context = new BuildContext(model: new ModelInfo(name: 'foo', tunable: true));

        $payload = ['temperature' => 0.7];
        $builder->build($payload, $context);
    }

    public function testNoLoggerDoesNotThrow(): void
    {
        $builder = new TuningStripBuilder();
        $context = new BuildContext(model: new ModelInfo(name: 'gpt-5', tunable: false));

        $payload = ['temperature' => 0.7];

        $result = $builder->build($payload, $context);

        $this->assertArrayNotHasKey('temperature', $result);
    }
}
