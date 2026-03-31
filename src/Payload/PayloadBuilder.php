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

        if ($value instanceof JsonSerializable) {
            foreach ($this->builders as $builder) {
                if ($builder->supports($value, $context)) {
                    $value = $builder->build($value, $context);
                    break;
                }
            }

            if ($value instanceof JsonSerializable) {
                $value = $value->jsonSerialize();
            }
        }

        if (is_array($value)) {
            return array_map(
                fn(mixed $item) => $this->build($item, $context),
                $value,
            );
        }

        return $value;
    }
}
