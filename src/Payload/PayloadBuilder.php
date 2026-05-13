<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload;

use JsonSerializable;

readonly class PayloadBuilder
{
    /** @var BuilderInterface[] */
    private array $builders;

    /**
     * @param iterable<BuilderInterface> $builders
     */
    public function __construct(iterable $builders = [])
    {
        $this->builders = iterator_to_array($builders, false);
    }

    public function build(mixed $value, ?BuildContext $context = null): mixed
    {
        $context ??= new BuildContext();

        if (null === $value || is_scalar($value)) {
            return $value;
        }

        // Phase 1: transform JsonSerializable values through matching builders.
        // The first builder that returns a non-JsonSerializable value (typically an array)
        // ends the JsonSerializable phase.
        if ($value instanceof JsonSerializable) {
            foreach ($this->builders as $builder) {
                if ($builder->supports($value, $context)) {
                    $value = $builder->build($value, $context);
                    if (!($value instanceof JsonSerializable)) {
                        break;
                    }
                }
            }

            if ($value instanceof JsonSerializable) {
                $value = $value->jsonSerialize();
            }
        }

        // Phase 2: post-process arrays through any builder that supports array values.
        // All matching builders are applied sequentially, allowing post-processors
        // (e.g. parameter stripping) to chain after provider-specific transformers.
        if (is_array($value)) {
            foreach ($this->builders as $builder) {
                if ($builder->supports($value, $context)) {
                    $value = $builder->build($value, $context);
                }
            }

            if (is_array($value)) {
                return array_map(
                    fn(mixed $item) => $this->build($item, $context),
                    $value,
                );
            }
        }

        return $value;
    }
}
