<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Payload;

use ByCerfrance\LlmApiLib\Chat\ReasoningEffort;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Model\ParameterPolicy;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\ModelChatBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ModelChatBuilder::class)]
#[UsesClass(Model::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(ChatPayload::class)]
#[UsesClass(PayloadBuilder::class)]
class ModelChatBuilderTest extends TestCase
{
    public function testSupportsOnlyRootPayloadWithModel(): void
    {
        $builder = new ModelChatBuilder();
        $this->assertTrue($builder->supports(new ChatPayload([]), new BuildContext(model: new Model('model'))));
        $this->assertFalse($builder->supports(new ChatPayload([]), new BuildContext()));
        $this->assertFalse($builder->supports([], new BuildContext(model: new Model('model'))));
    }

    public function testCompatiblePayloadIsKept(): void
    {
        $payload = ['temperature' => 0, 'max_completion_tokens' => 10, 'reasoning_effort' => ReasoningEffort::HIGH];
        $model = new Model('model', maxOutputTokens: 10, parameterValues: ['reasoning_effort' => ['high']]);
        $result = (new ModelChatBuilder())->build(new ChatPayload($payload), new BuildContext(model: $model));
        $this->assertSame($payload, $result->jsonSerialize());
    }

    /** @return iterable<string, array{Model, array<string, mixed>, string}> */
    public static function incompatiblePayloads(): iterable
    {
        yield 'fixed sampling' => [new Model('model', tunable: false), ['temperature' => 0], 'temperature'];
        yield 'unsupported path' => [new Model('model', unsupportedParameters: ['response_format.json_schema.strict']),
            ['response_format' => ['type' => 'json_schema', 'json_schema' => ['strict' => true]]], 'strict'];
        yield 'disallowed value' => [new Model('model', parameterValues: ['reasoning_effort' => ['high']]), ['reasoning_effort' => ReasoningEffort::LOW], 'reasoning_effort'];
        yield 'output limit' => [new Model('model', maxOutputTokens: 100), ['max_tokens' => 200], '100-token limit'];
        yield 'json format' => [new Model('model', capabilities: [Capability::TEXT]), ['response_format' => ['type' => 'json_object']], 'json'];
        yield 'reasoning' => [new Model('model', capabilities: [Capability::TEXT]), ['reasoning_effort' => 'low'], 'reasoning'];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('incompatiblePayloads')]
    public function testStrictPolicyRejectsIncompatibleParameters(Model $model, array $payload, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        (new ModelChatBuilder())->build(new ChatPayload($payload), new BuildContext(model: $model));
    }

    public function testPermissivePolicyOmitsAndReportsWithoutTouchingSchemas(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning')->with('Unsupported generation parameter omitted.', $this->anything());
        $schema = ['type' => 'object', 'properties' => ['temperature' => ['type' => 'number']]];
        $model = new Model('fixed', tunable: false, unsupportedParameters: ['response_format.json_schema.strict']);
        $result = (new ModelChatBuilder(ParameterPolicy::OMIT_UNSUPPORTED, $logger))->build(new ChatPayload([
            'temperature' => 0,
            'response_format' => ['type' => 'json_schema', 'json_schema' => ['strict' => true, 'schema' => $schema]],
        ]), new BuildContext(model: $model))->jsonSerialize();
        $this->assertArrayNotHasKey('temperature', $result);
        $this->assertArrayNotHasKey('strict', $result['response_format']['json_schema']);
        $this->assertSame($schema, $result['response_format']['json_schema']['schema']);
    }

    public function testPermissivePolicyRequiresALogger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ModelChatBuilder(ParameterPolicy::OMIT_UNSUPPORTED);
    }
}
