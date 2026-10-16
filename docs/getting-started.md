# Getting started

[Back to documentation](README.md)

## Goal

Send a prompt to OpenAI and print the answer. You will create three objects:

- A **client** sends HTTP requests.
- A **provider** knows the API's authentication and message format.
- A **Chat** binds that provider to a model and executes conversations.

## Install

Use PHP 8.3 or newer. These examples use Berlioz's PSR-18 client and its cURL adapter, so enable PHP's `curl` extension.

```shell
composer require bycerfrance/llm-api-lib:dev-2.x berlioz/http-client
```

The `2.x` branch prepares the v2 API; this command explicitly selects that development branch. You can use another PSR-18 client in your application.

Set `OPENAI_API_KEY` and `OPENAI_MODEL` in your process environment. Choose a Chat Completions model available to your account. For example, in PowerShell:

```powershell
$env:OPENAI_API_KEY = 'your-api-key'
$env:OPENAI_MODEL = 'your-model-id'
```

On a POSIX shell:

```shell
export OPENAI_API_KEY='your-api-key'
export OPENAI_MODEL='your-model-id'
```

Replace the placeholders. The library does not load `.env` files itself.

## Shared setup

Create `bootstrap.php` in your project root, next to `vendor/`. Later guides reuse this file.

```php
<?php

declare(strict_types=1);

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAi;

require __DIR__ . '/vendor/autoload.php';

$apiKey = getenv('OPENAI_API_KEY') ?: throw new RuntimeException('Set OPENAI_API_KEY.');
$modelName = getenv('OPENAI_MODEL') ?: throw new RuntimeException('Set OPENAI_MODEL.');
$client = new CurlAdapter();
$provider = new OpenAi(apiKey: $apiKey, client: $client);
$model = new Model($modelName);
$chat = new Chat(provider: $provider, model: $model);
```

This creates `$client`, `$provider`, `$model` and `$chat`, but sends no request. The credentials and model name are also available to later examples.

## Your first request

Create `hello.php` next to `bootstrap.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$response = $chat->chat('Explain crop rotation in one sentence.');
$message = $response->getResult()->getMessage();

echo (string) $message->getContent() . PHP_EOL;
```

Run `php hello.php`. A successful call prints a sentence about crop rotation. The wording is not deterministic.

`getResult()` gives you the final provider turn. `getMessage()->getContent()` gives you its content. Casting to string is appropriate for this text-only example; image responses should be inspected as parts instead.

## Try it offline

From this repository, run:

```shell
composer install
php examples/chat.php
```

That [script](../examples/chat.php) uses a local HTTP fixture and does not require a key. It shows two exchanges and a branch, with known history lengths.

## Common mistakes

- Pass the model to `Chat`, not to `OpenAi`.
- Use a real provider model ID, not the literal `your-model-id` placeholder.
- A bare prompt starts a new conversation. To retain history, use the returned conversation in your next request.

Next: [Conversations](conversations.md). See also [Providers](providers.md).
