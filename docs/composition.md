# Application composition

[Back to documentation](README.md)

## Goal

Keep application roles separate from provider details. Use ChatInterface for conversational work and ImageGeneratorInterface for standalone images. An orchestrator can register either without inventing one universal execution interface.

## Register executors by role

This real-request example uses the [shared bootstrap](getting-started.md#shared-setup) and an additional `OPENAI_IMAGE_MODEL` environment variable.

```php
<?php

declare(strict_types=1);

use ByCerfrance\LlmApiLib\Chat\ChatInterface;
use ByCerfrance\LlmApiLib\Image\ImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageGeneratorInterface;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAiImages;

require __DIR__ . '/bootstrap.php';

$imageModel = getenv('OPENAI_IMAGE_MODEL') ?: throw new RuntimeException('Set OPENAI_IMAGE_MODEL.');

/** @var array<string, ChatInterface> $chats */
$chats = ['writer' => $chat, 'reviewer' => $chat];

/** @var array<string, ImageGeneratorInterface> $images */
$images = ['illustrator' => new ImageGenerator(new OpenAiImages($apiKey, $client), new Model($imageModel))];

$draft = $chats['writer']->chat('Write one sentence about sustainable farming.');
$review = $chats['reviewer']->chat(
    'Suggest one improvement to this sentence: ' . (string) $draft->getResult()->getMessage()->getContent(),
);
echo (string) $review->getResult()->getMessage()->getContent() . PHP_EOL;
```

The names `writer`, `reviewer` and `illustrator` belong to your application. Here both chat roles reuse one executor; each string request starts a separate conversation. You can replace either entry with a differently configured Chat or a compatible decorator.

The image executor is registered but not called in this example. Calling `$images['illustrator']->generate('A farm illustration')` returns an ImageResponse, not a ChatResponse.

## Delegate through a tool

Add this variant after the main example to let the writer ask the reviewer for help:

```php
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Tool\ChatTool;
use ByCerfrance\LlmApiLib\Tool\ToolCollection;

$reviewTool = new ChatTool(
    name: 'review_sentence',
    description: 'Ask a reviewer to suggest one improvement to a sentence.',
    parameters: [
        'type' => 'object',
        'properties' => ['sentence' => ['type' => 'string']],
        'required' => ['sentence'],
        'additionalProperties' => false,
    ],
    chat: $chats['reviewer'],
    promptBuilder: static fn(array $arguments): string => 'Suggest one improvement: ' . $arguments['sentence'],
);
$response = $chats['writer']->chat(new ChatRequest(
    new Conversation(['Write a sentence about farming, ask the reviewer for feedback, then improve it.']),
    tools: new ToolCollection($reviewTool),
));
echo (string) $response->getResult()->getMessage()->getContent() . PHP_EOL;
```

If the model calls the tool, ChatTool sends a separate request to the reviewer and returns its content as a tool result. A prompt builder can return a ChatRequest when the child needs system instructions or options. The reviewer in this example has no tools, so it does not recursively call itself.

## Composition rules

- Apply retry/failover around provider turns when tools are involved.
- For cumulative delegated usage, observe each actual provider with a shared collector. Parent response totals do not include child ChatTool usage.
- Register streaming executors separately or check StreamingChatInterface; a synchronous decorator does not automatically support streaming.
- Keep conversation history in the workflow that owns it. A role name does not imply shared conversational state.
- Choose models, priorities and role-specific instructions in your application; there are no built-in quality scores or selection tiers.

Executable building blocks: [tools](../examples/tools.php), [resilience](../examples/resilience.php), [usage](../examples/usage.php).

Related: [Tools](tools.md), [Usage and pricing](usage-and-pricing.md), [Retries and failover](retries-and-failover.md).
