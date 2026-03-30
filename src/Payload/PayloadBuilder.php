<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload;

use ByCerfrance\LlmApiLib\Payload\Builder\CompletionBuilder;
use ByCerfrance\LlmApiLib\Payload\Builder\ContentBuilder;
use ByCerfrance\LlmApiLib\Payload\Builder\MessageBuilder;
use ByCerfrance\LlmApiLib\Payload\Builder\ResponseFormatBuilder;
use ByCerfrance\LlmApiLib\Payload\Builder\ToolBuilder;
use RuntimeException;

readonly class PayloadBuilder
{
    /** @var BuilderInterface[] */
    private array $builders;

    /**
     * @param iterable<BuilderInterface> $builders
     */
    public function __construct(iterable $builders = [])
    {
        $this->builders = [
            ...iterator_to_array($builders, false),
            ...self::defaultBuilders(),
        ];
    }

    public function build(mixed $value, ?BuildContext $context = null): mixed
    {
        $context ??= new BuildContext();

        if (is_array($value)) {
            return array_map(
                fn(mixed $item) => $this->build($item, $context),
                $value,
            );
        }

        if (!is_object($value)) {
            return $value;
        }

        foreach ($this->builders as $builder) {
            if ($builder->supports($value, $context)) {
                return $builder->build($value, $this, $context);
            }
        }

        throw new RuntimeException(sprintf('No payload builder found for "%s"', $value::class));
    }

    /**
     * @return list<BuilderInterface>
     */
    private static function defaultBuilders(): array
    {
        return [
            new CompletionBuilder(),
            new MessageBuilder(),
            new ContentBuilder(),
            new ToolBuilder(),
            new ResponseFormatBuilder(),
        ];
    }
}
