<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Message;

use ByCerfrance\LlmApiLib\Completion\Content\ContentFactory;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use InvalidArgumentException;

/**
 * Factory for creating message instances from API response data.
 */
readonly class MessageFactory
{
    /**
     * Create a message from API response message data.
     *
     * @param array $messageData The 'message' portion of an API response choice
     *
     * @return MessageInterface
     *
     * @throws InvalidArgumentException
     */
    public static function create(array $messageData): MessageInterface
    {
        $role = RoleEnum::from($messageData['role'] ?? '');
        $content = ContentFactory::create($messageData['content'] ?? null);

        return match (true) {
            $role === RoleEnum::ASSISTANT => new AssistantMessage(
                content: $content,
                toolCalls: array_map(
                    fn(array $tc) => ToolCall::fromArray($tc),
                    $messageData['tool_calls'] ?? [],
                ),
            ),
            $role === RoleEnum::SYSTEM => new SystemMessage($content),
            default => new UserMessage($content),
        };
    }
}
