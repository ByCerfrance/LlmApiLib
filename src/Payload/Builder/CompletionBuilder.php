<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload\Builder;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;

readonly class CompletionBuilder implements BuilderInterface
{
    public function __construct(
        private bool $maxCompletionTokens = true,
    ) {
    }

    public function supports(mixed $value, BuildContext $context): bool
    {
        return $value instanceof CompletionInterface;
    }

    /**
     * @param CompletionInterface $value
     */
    public function build(mixed $value, PayloadBuilder $payloadBuilder, BuildContext $context): array
    {
        $maxTokensKey = $this->maxCompletionTokens ? 'max_completion_tokens' : 'max_tokens';

        return array_filter(
            [
                $maxTokensKey => $value->getMaxTokens(),
                'messages' => $payloadBuilder->build(iterator_to_array($value->getIterator(), false), $context),
                'model' => null !== $value->getModel() ? (string)$value->getModel() : null,
                'response_format' => null !== $value->getResponseFormat()
                    ? $payloadBuilder->build($value->getResponseFormat(), $context)
                    : null,
                'stream' => false,
                'temperature' => $value->getTemperature(),
                'top_p' => $value->getTopP(),
                'seed' => $value->getSeed(),
                'tools' => null !== $value->getTools()
                    ? $payloadBuilder->build($value->getTools(), $context)
                    : null,
            ],
            fn($v) => null !== $v,
        );
    }
}
