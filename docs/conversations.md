# Conversations

[Back to documentation](README.md)

## Goal

Keep a system instruction, ask a follow-up question and branch from an earlier answer. Use the [shared bootstrap](getting-started.md#shared-setup).

## A complete two-exchange example

```php
<?php

declare(strict_types=1);

use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\SystemMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;

require __DIR__ . '/bootstrap.php';

$original = new Conversation([
    new SystemMessage('You explain farming to beginners. Use short answers.'),
    'What is crop rotation?',
]);
$request = new ChatRequest($original);
$first = $chat->chat($request);

$followUp = $first->toConversation()->withNewMessage('Give a three-year example.');
$second = $chat->chat($request->withConversation($followUp));

echo (string) $second->getResult()->getMessage()->getContent() . PHP_EOL;
echo 'Original messages: ' . count($original) . PHP_EOL;
echo 'First answer history: ' . count($first->toConversation()) . PHP_EOL;
echo 'Second answer history: ' . count($second->toConversation()) . PHP_EOL;
```

Without tools, the counts are **2, 3 and 5**. The second request includes the original system instruction, question, first answer and follow-up question.

`withNewMessage()` returns a new conversation. It does not modify the old one. A string becomes a user message. Likewise, `withConversation()` returns a new request and preserves its other options.

## Create a branch

Add this after the main example:

```php
$alternative = $first->toConversation()->withNewMessage('Explain it to a child instead.');
$branch = $chat->chat($request->withConversation($alternative));

echo (string) $branch->getResult()->getMessage()->getContent() . PHP_EOL;
```

The branch starts from `$first`, not `$second`. It does not contain the three-year example. Both later conversations have five messages, and the first history still has three.

## What to keep

- Keep `toConversation()` for the next exchange, including tool calls/results and native provider metadata.
- Keep the request separately if you want to reuse its tools, response format or output modalities.
- `getTurns()` contains the provider turns for this execution; a tool conversation can have more than one.
- `getUsage()` totals this execution, not all earlier exchanges. Use [cumulative accounting](usage-and-pricing.md) for that.

Do not rebuild a native image conversation from visible text alone: that would lose images and thought signatures. Custom messages/content placed in a conversation must themselves be immutable.

Offline: [examples/chat.php](../examples/chat.php).

Related: [Tools](tools.md), [Images](images.md), [Models and options](models-and-options.md).
