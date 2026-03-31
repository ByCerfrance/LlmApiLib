<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Payload;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\Builder\MistralCompletionBuilder;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(PayloadBuilder::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Message::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(MistralCompletionBuilder::class)]
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

    public function testBuildCompletionPayloadCanOverrideToLegacyKey(): void
    {
        $builder = new PayloadBuilder([
            new MistralCompletionBuilder(),
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

    public function testBuildScalarPassesThrough(): void
    {
        $builder = new PayloadBuilder();

        $this->assertSame(42, $builder->build(42));
        $this->assertSame('hello', $builder->build('hello'));
        $this->assertSame(true, $builder->build(true));
        $this->assertNull($builder->build(null));
    }

    public function testBuildArrayRecursesElements(): void
    {
        $builder = new PayloadBuilder();

        $result = $builder->build([1, 'two', null, true]);

        $this->assertSame([1, 'two', null, true], $result);
    }

    public function testBuildJsonSerializableFallback(): void
    {
        $builder = new PayloadBuilder();

        $obj = new class implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['key' => 'value'];
            }
        };

        $result = $builder->build($obj);

        $this->assertSame(['key' => 'value'], $result);
    }

    public function testBuildRecursesObjectsInsideArrays(): void
    {
        $builder = new PayloadBuilder();

        $inner = new class implements JsonSerializable {
            public function jsonSerialize(): string
            {
                return 'resolved';
            }
        };

        $result = $builder->build(['a' => $inner, 'b' => 42]);

        $this->assertSame(['a' => 'resolved', 'b' => 42], $result);
    }

    public function testBuildNonJsonSerializableObjectPassesThrough(): void
    {
        $builder = new PayloadBuilder();

        $obj = new stdClass();
        $obj->foo = 'bar';

        $result = $builder->build($obj);

        $this->assertSame($obj, $result);
    }

    public function testCustomBuilderTakesPriorityOverFallback(): void
    {
        $customBuilder = new class implements BuilderInterface {
            public function supports(mixed $value, BuildContext $context): bool
            {
                return $value instanceof Completion;
            }

            public function build(mixed $value, BuildContext $context): mixed
            {
                return ['custom' => true];
            }
        };

        $builder = new PayloadBuilder([$customBuilder]);
        $payload = $builder->build(new Completion(messages: [new Message('hello')]));

        $this->assertSame(['custom' => true], $payload);
    }

    public function testBuilderResultIsRecursed(): void
    {
        $inner = new class implements JsonSerializable {
            public function jsonSerialize(): string
            {
                return 'inner-resolved';
            }
        };

        $customBuilder = new class($inner) implements BuilderInterface {
            public function __construct(private readonly JsonSerializable $inner)
            {
            }

            public function supports(mixed $value, BuildContext $context): bool
            {
                return $value instanceof Completion;
            }

            public function build(mixed $value, BuildContext $context): array
            {
                return ['nested' => $this->inner];
            }
        };

        $builder = new PayloadBuilder([$customBuilder]);
        $payload = $builder->build(new Completion(messages: [new Message('hello')]));

        $this->assertSame(['nested' => 'inner-resolved'], $payload);
    }
}
