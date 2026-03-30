<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Payload;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\Builder\CompletionBuilder;
use ByCerfrance\LlmApiLib\Payload\Builder\ContentBuilder;
use ByCerfrance\LlmApiLib\Payload\Builder\MessageBuilder;
use ByCerfrance\LlmApiLib\Payload\Builder\ResponseFormatBuilder;
use ByCerfrance\LlmApiLib\Payload\Builder\ToolBuilder;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PayloadBuilder::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(CompletionBuilder::class)]
#[UsesClass(MessageBuilder::class)]
#[UsesClass(ContentBuilder::class)]
#[UsesClass(ToolBuilder::class)]
#[UsesClass(ResponseFormatBuilder::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(TextContent::class)]
class PayloadBuilderTest extends TestCase
{
    public function testBuildDefaultCompletionPayloadUsesModernKey(): void
    {
        $builder = new PayloadBuilder();
        $payload = $builder->build(
            new Completion(
                messages: [new Message('hello')],
            ),
        );

        $this->assertArrayHasKey('max_completion_tokens', $payload);
        $this->assertArrayNotHasKey('max_tokens', $payload);
        $this->assertSame(1000, $payload['max_completion_tokens']);
    }

    public function testBuildCompletionPayloadCanOverrideDefaultBuilderToLegacyKey(): void
    {
        $builder = new PayloadBuilder([
            new CompletionBuilder(maxCompletionTokens: false),
        ]);

        $payload = $builder->build(
            new Completion(
                messages: [new Message('hello')],
            ),
        );

        $this->assertArrayHasKey('max_tokens', $payload);
        $this->assertArrayNotHasKey('max_completion_tokens', $payload);
        $this->assertSame(1000, $payload['max_tokens']);
    }

    public function testBuildThrowsOnUnsupportedObject(): void
    {
        $builder = new PayloadBuilder();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No payload builder found for "stdClass"');

        $builder->build(new \stdClass());
    }

    public function testCustomBuilderTakesPriorityOverDefault(): void
    {
        $customBuilder = new class implements BuilderInterface {
            public function supports(mixed $value, BuildContext $context): bool
            {
                return $value instanceof Completion;
            }

            public function build(mixed $value, PayloadBuilder $payloadBuilder, BuildContext $context): mixed
            {
                return ['custom' => true];
            }
        };

        $builder = new PayloadBuilder([$customBuilder]);
        $payload = $builder->build(new Completion(messages: [new Message('hello')]));

        $this->assertSame(['custom' => true], $payload);
    }
}
