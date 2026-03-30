<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload\Builder;

use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollectionInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolInterface;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;

readonly class ToolBuilder implements BuilderInterface
{
    public function supports(mixed $value, BuildContext $context): bool
    {
        return $value instanceof ToolCollectionInterface
            || $value instanceof ToolInterface
            || $value instanceof ToolCall;
    }

    /**
     * @param ToolCollectionInterface|ToolInterface|ToolCall $value
     */
    public function build(mixed $value, PayloadBuilder $payloadBuilder, BuildContext $context): mixed
    {
        if ($value instanceof ToolCollectionInterface) {
            return $payloadBuilder->build(iterator_to_array($value->getIterator(), false), $context);
        }

        if ($value instanceof ToolCall) {
            $payload = [
                'id' => $value->id,
                'type' => 'function',
                'function' => [
                    'name' => $value->name,
                    'arguments' => json_encode($value->arguments),
                ],
            ];

            if (null !== $value->additionalFields) {
                $payload = array_merge($payload, $value->additionalFields);
            }

            return $payload;
        }

        /** @var ToolInterface $value */
        return [
            'type' => 'function',
            'function' => [
                'name' => $value->getName(),
                'description' => $value->getDescription(),
                'parameters' => (object)$value->getParameters(),
            ],
        ];
    }
}
