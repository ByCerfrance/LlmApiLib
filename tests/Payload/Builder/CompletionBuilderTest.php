<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Payload\Builder;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\Builder\CompletionBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Tests\Payload\Support\PayloadReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompletionBuilder::class)]
class CompletionBuilderTest extends TestCase
{
    public function testSupportsCompletionOnly(): void
    {
        $builder = new CompletionBuilder();

        $this->assertTrue($builder->supports(new Completion(messages: []), new BuildContext()));
        $this->assertFalse($builder->supports(new \stdClass(), new BuildContext()));
    }

    public function testBuildUsesMaxCompletionTokensByDefault(): void
    {
        $builder = new CompletionBuilder();
        $completion = new Completion(messages: [new Message('hello')], maxTokens: 123);

        $payload = $builder->build($completion, new PayloadBuilder(), new BuildContext());

        $this->assertSame(PayloadReference::completion($completion, true), $payload);
    }

    public function testBuildCanUseLegacyMaxTokens(): void
    {
        $builder = new CompletionBuilder(maxCompletionTokens: false);
        $completion = new Completion(messages: [new Message('hello')], maxTokens: 321);

        $payload = $builder->build($completion, new PayloadBuilder(), new BuildContext());

        $this->assertSame(PayloadReference::completion($completion, false), $payload);
    }

    public function testBuildWithNullToolsAndNullResponseFormat(): void
    {
        $builder = new CompletionBuilder();
        $completion = new Completion(messages: [new Message('hello')]);

        $payload = $builder->build($completion, new PayloadBuilder(), new BuildContext());

        $this->assertArrayNotHasKey('tools', $payload);
        $this->assertArrayNotHasKey('response_format', $payload);
        $this->assertArrayNotHasKey('model', $payload);
        $this->assertArrayNotHasKey('seed', $payload);
    }
}
