<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload\Builder;

use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;

readonly class MessageBuilder implements BuilderInterface
{
    public function supports(mixed $value, BuildContext $context): bool
    {
        return $value instanceof MessageInterface;
    }

    /**
     * @param MessageInterface $value
     */
    public function build(mixed $value, PayloadBuilder $payloadBuilder, BuildContext $context): array
    {
        if ($value instanceof ToolResult) {
            return [
                'role' => $value->getRole()->value,
                'tool_call_id' => $value->getToolCallId(),
                'content' => (string)$value->getContent(),
            ];
        }

        if ($value instanceof AssistantMessage && $value->hasToolCalls()) {
            return [
                'role' => $value->getRole()->value,
                'content' => null,
                'tool_calls' => $payloadBuilder->build($value->getToolCalls(), $context),
            ];
        }

        return [
            'role' => $value->getRole()->value,
            'content' => $payloadBuilder->build($value->getContent(), $context),
        ];
    }
}
