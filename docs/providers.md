# Providers

[Back to documentation](README.md)

## Goal

Change the API endpoint without changing the conversation code. A provider handles authentication and protocol mapping; Chat owns the model and tool loop.

## Use a compatible provider

This real-request example uses Mistral. It needs `MISTRAL_API_KEY` and `MISTRAL_MODEL`, plus the packages installed in [Getting started](getting-started.md).

```php
<?php

declare(strict_types=1);

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\Mistral\Mistral;

require __DIR__ . '/vendor/autoload.php';

$provider = new Mistral(
    apiKey: getenv('MISTRAL_API_KEY') ?: throw new RuntimeException('Set MISTRAL_API_KEY.'),
    client: new CurlAdapter(),
);
$model = new Model(getenv('MISTRAL_MODEL') ?: throw new RuntimeException('Set MISTRAL_MODEL.'));
$chat = new Chat($provider, $model);
echo (string) $chat->chat('Say hello.')->getResult()->getMessage()->getContent() . PHP_EOL;
```

The response is a short greeting. Provider/model availability determines the exact answer.

## Choose an adapter

Each provider lives in its own namespace under `Provider\`:

| Class | Purpose |
| --- | --- |
| `OpenAi\OpenAi` | OpenAI Chat Completions |
| `OpenAi\StreamingOpenAi` | OpenAI chat with an explicit progressive transport |
| `OpenAi\OpenAiImages` | Standalone image generation |
| `Mistral\Mistral` | Mistral chat with provider-specific parameter mapping |
| `Ovh\Ovh` | OVH AI Endpoints chat |
| `Google\Google` | Google's OpenAI compatibility endpoint |
| `Google\GoogleNative` | Native Gemini v1beta GenerateContent, including conversational images |
| `OpenAiCompatible\Generic` | An explicitly configured OpenAI-compatible endpoint |

Compatible providers extend `OpenAiCompatible\AbstractOpenAiCompatible`. They accept a PSR-18 client, API key, optional ID, extra body fields and logger. Native and image providers have their own constructor options.

Provider-independent contracts and decorators (`ChatProviderInterface`, `RetryProvider`, `FailoverProvider`, `ProviderException`…) stay directly under `Provider\`. See [Architecture](architecture.md) for the module rules.

## A custom endpoint

With `$client` from the [shared bootstrap](getting-started.md#shared-setup), configure a Generic provider:

```php
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\Generic;

$provider = new Generic(
    uri: getenv('LLM_ENDPOINT') ?: throw new RuntimeException('Set LLM_ENDPOINT.'),
    apiKey: getenv('LLM_API_KEY') ?: '',
    client: $client,
    id: 'local-chat',
);
```

`LLM_ENDPOINT` must be the **full request URL**, such as `http://localhost:8000/v1/chat/completions`, not just the host. Construct a new Chat with this provider and the model served by that endpoint. Compatibility does not imply support for every OpenAI option.

## Provider-specific differences

- Mistral maps the maximum-token field to `max_tokens` and rejects incompatible service-tier/reasoning settings.
- Google compatibility preserves explicit settings when model support is unknown. Conflicting reasoning-effort and native thinking configuration is rejected.
- GoogleNative preserves protocol-specific history for image editing and tools. Do not transfer that history to a different model/provider.
- Extra body fields cannot replace model/history, override configured options or silently turn on streaming/tools/multiple choices.

Offline adapter selection: `php examples/chat.php mistral` (also `openai`, `google`, `ovh`, `generic`). These are [fixtures](../examples/chat.php), not live integration checks.

Related: [Models and options](models-and-options.md), [Images](images.md), [Streaming](streaming.md), [Retries and failover](retries-and-failover.md).
