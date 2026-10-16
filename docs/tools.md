# Tools

[Back to documentation](README.md)

## Goal

Let the model ask your application to double an integer. Use the [shared bootstrap](getting-started.md#shared-setup) with a model supporting function tools.

## Define and use a PHP tool

```php
<?php

declare(strict_types=1);

use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Completion\Tool\Tool;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollection;
use ByCerfrance\LlmApiLib\Completion\ToolChoice;
use ByCerfrance\LlmApiLib\Conversation\Conversation;

require __DIR__ . '/bootstrap.php';

$double = new Tool(
    name: 'double',
    description: 'Double an integer.',
    parameters: [
        'type' => 'object',
        'properties' => ['value' => ['type' => 'integer']],
        'required' => ['value'],
        'additionalProperties' => false,
    ],
    callback: static fn(array $arguments): string => (string) ($arguments['value'] * 2),
);
$request = new ChatRequest(
    conversation: new Conversation(['Use the tool to double 21.']),
    tools: new ToolCollection($double),
    toolChoice: ToolChoice::REQUIRED,
    maxToolIterations: 3,
);
$response = $chat->chat($request);
echo (string) $response->getResult()->getMessage()->getContent() . PHP_EOL;
```

The expected answer contains **42**. Real model wording and number of turns can vary. For a deterministic run, use [examples/tools.php](../examples/tools.php), which prints `Double: 42`, two provider turns and four history messages.

## What happens during the call

1. Chat sends the prompt and tool schema to the provider.
2. The model requests `double` with JSON arguments.
3. Chat validates the arguments before running your callback.
4. Chat appends the invocation and result to history, then requests the final answer.

REQUIRED becomes AUTO after the first tool round so the model can finish. Multiple calls execute sequentially, even if `parallelToolCalls` allows the model to request several at once. `maxToolIterations` limits tool rounds, not individual calls; zero prevents execution.

## Errors

The default `ToolErrorPolicy::STOP` throws on unknown tools, invalid arguments or callback failures. This variant lets the model see a sanitized error result instead:

```php
use ByCerfrance\LlmApiLib\Completion\Tool\ToolErrorPolicy;

$request = $request->withToolErrorPolicy(ToolErrorPolicy::RETURN_ERROR);
```

Invalid schema configuration still stops execution. Tool argument JSON must describe an object. Original JSON preserves nested object/array distinctions during validation.

## Continuation and repeated calls

Pass the returned conversation into the next request to preserve completed tool results. Matching invocation IDs reuse those results; the same tool with a new ID can run again. Reusing an ID with different arguments is rejected.

The default registry lives for one execution and is rebuilt from completed history on continuation. An injected registry can support explicit recovery. It does not guarantee exactly-once external effects across crashes.

Related: [Retries and failover](retries-and-failover.md), [MCP](mcp.md), [OpenAPI](openapi.md), [Chat delegation](composition.md).
