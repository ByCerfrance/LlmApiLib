<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload\Builder;

use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use Override;
use Psr\Log\LoggerInterface;

/**
 * Strips unsupported parameters from a built payload array based on the resolved
 * {@see \ByCerfrance\LlmApiLib\Model\ModelInfo} carried by the {@see BuildContext}.
 *
 * - When `ModelInfo::$tunable` is false, removes every path listed in
 *   `ModelInfo::TUNING_PARAMETERS` (temperature, top_p, etc.).
 * - Always removes paths listed in `ModelInfo::$stripFields` (dot-notation
 *   supported for nested paths, e.g. `response_format.strict`).
 *
 * Operates as an array post-processor: it runs after JsonSerializable→array
 * transformers in {@see \ByCerfrance\LlmApiLib\Payload\PayloadBuilder::build()}.
 *
 * @internal
 */
final readonly class TuningStripBuilder implements BuilderInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function supports(mixed $value, BuildContext $context): bool
    {
        return is_array($value) && null !== $context->model;
    }

    #[Override]
    public function build(mixed $value, BuildContext $context): array
    {
        /** @var array<int|string, mixed> $value */
        $model = $context->model;
        if (null === $model) {
            return $value;
        }

        foreach ($model->getStrippedFields() as $path) {
            if (b_array_traverse_unset($value, $path)) {
                $this->logger?->warning(
                    'Parameter "{path}" stripped: not supported by model {model}',
                    ['path' => $path, 'model' => $model->name],
                );
            }
        }

        return $value;
    }
}
