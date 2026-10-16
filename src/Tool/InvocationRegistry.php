<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tool;

use Override;
use RuntimeException;

class InvocationRegistry implements InvocationRegistryInterface
{
    /** @var array<string, array{signature: string, result: ToolResult}> */
    private array $completed = [];

    #[Override]
    public function get(ToolCall $call): ?ToolResult
    {
        $entry = $this->completed[$call->id] ?? null;
        if ($entry !== null && $entry['signature'] !== $this->signature($call)) {
            throw new RuntimeException(sprintf('Tool invocation ID "%s" was reused with different arguments or tool name.', $call->id));
        }

        return $entry['result'] ?? null;
    }

    #[Override]
    public function record(ToolCall $call, ToolResult $result): void
    {
        if ($call->id !== $result->getToolCallId()) {
            throw new RuntimeException('Tool result ID does not match its invocation.');
        }
        if ($this->get($call) === null) {
            $this->completed[$call->id] = ['signature' => $this->signature($call), 'result' => $result];
        }
    }

    private function signature(ToolCall $call): string
    {
        return json_encode([$call->name, json_decode($call->getArgumentsJson(), flags: JSON_THROW_ON_ERROR)], JSON_THROW_ON_ERROR);
    }
}
