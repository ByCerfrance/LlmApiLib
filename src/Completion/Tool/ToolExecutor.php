<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Tool;

use ByCerfrance\LlmApiLib\Observation\ExecutionEvent;
use ByCerfrance\LlmApiLib\Observation\ExecutionObserverInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

readonly class ToolExecutor
{
    public function __construct(
        private ArgumentsValidatorInterface $validator = new JsonSchemaArgumentsValidator(),
        private ?ExecutionObserverInterface $observer = null,
    )
    {
    }

    public function execute(
        ToolCall $call,
        ToolCollectionInterface $tools,
        InvocationRegistryInterface $registry,
        ToolErrorPolicy $policy,
    ): ToolResult {
        if (($completed = $registry->get($call)) !== null) {
            $this->observer?->observe(new ExecutionEvent($call->id, 'tool.reused', $call->name));
            return $completed;
        }
        $this->observer?->observe(new ExecutionEvent($call->id, 'tool.started', $call->name));
        $errorType = 'unknown_tool';
        try {
            $tool = $tools->get($call->name);
            $errorType = 'invalid_arguments';
            $this->validator->validate($tool, $call);
            $errorType = 'execution_failed';
            $result = $tools->execute($call);
        } catch (Throwable $exception) {
            $this->observer?->observe(new ExecutionEvent($call->id, 'tool.failed', $call->name, failureKind: $errorType));
            if ($policy === ToolErrorPolicy::STOP) {
                throw new RuntimeException(sprintf('Tool "%s" failed (%s).', $call->name, $errorType), previous: $exception);
            }
            // Schema configuration errors cannot be repaired by changing model arguments.
            if ($errorType === 'invalid_arguments' && !$exception instanceof InvalidArgumentException) {
                throw $exception;
            }
            $result = new ToolResult($call->id, ['error' => ['type' => $errorType, 'message' => match ($errorType) {
                'unknown_tool' => 'The requested tool is not available.',
                'invalid_arguments' => 'Arguments do not match the tool JSON Schema.',
                default => 'Tool execution failed.',
            }]]);
        }
        $registry->record($call, $result);
        $this->observer?->observe(new ExecutionEvent($call->id, 'tool.completed', $call->name));

        return $result;
    }
}
