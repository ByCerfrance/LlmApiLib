# Conversations

[Back to documentation](README.md)

## Goal

Keep a system instruction, ask a follow-up question and branch from an earlier answer. Use the [shared bootstrap](getting-started.md#shared-setup).

## A complete two-exchange example

```php
<?php

declare(strict_types=1);

use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\SystemMessage;

require __DIR__ . '/bootstrap.php';

$original = new Conversation([
    new SystemMessage('You explain farming to beginners. Use short answers.'),
    'What is crop rotation?',
]);
$request = new ChatRequest($original);
$first = $chat->chat($request);

$second = $chat->chat($first->reply('Give a three-year example.'));

echo $second->getText() . PHP_EOL;
echo 'Original messages: ' . count($original) . PHP_EOL;
echo 'First answer history: ' . count($first->toConversation()) . PHP_EOL;
echo 'Second answer history: ' . count($second->toConversation()) . PHP_EOL;
```

Without tools, the counts are **2, 3 and 5**. The second request includes the original system instruction, question, first answer and follow-up question.

`reply()` returns a new request: the original request's options, the complete history of `$first` and your new user message. It does not modify the response or the original request. Several arguments form a single multipart message, for example `$first->reply('Use this reference:', new ImageUrlContent($url))`. `getText()` returns the text of the final answer, or null when it has none.

## Create a branch

Add this after the main example:

```php
$branch = $chat->chat($first->reply('Explain it to a child instead.'));

echo $branch->getText() . PHP_EOL;
```

The branch starts from `$first`, not `$second`. It does not contain the three-year example. Both later conversations have five messages, and the first history still has three.

## What to keep

- Continue from a response with `reply()`: its history includes tool calls/results and native provider metadata, and the original request's tools, response format and output modalities are kept.
- Executor defaults (for example the Chat's maximum tokens) still apply to each call; they are not copied into the request.
- For full control, build the next request yourself with `$request->withConversation($response->toConversation()->withNewMessage(...))`.
- `getTurns()` contains the provider turns for this execution; a tool conversation can have more than one.
- `getUsage()` totals this execution, not all earlier exchanges. Use [cumulative accounting](usage-and-pricing.md) for that.

Do not rebuild a native image conversation from visible text alone: that would lose images and thought signatures. Custom messages/content placed in a conversation must themselves be immutable.

Offline: [examples/chat.php](../examples/chat.php).

Related: [Tools](tools.md), [Images](images.md), [Models and options](models-and-options.md).
