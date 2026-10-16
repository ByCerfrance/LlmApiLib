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
use ByCerfrance\LlmApiLib\Provider\Mistral;

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

| Class under `Provider\` | Purpose |
| --- | --- |
| OpenAi | OpenAI Chat Completions |
| Mistral | Mistral chat with provider-specific parameter mapping |
| Ovh | OVH AI Endpoints chat |
| Generic | An explicitly configured OpenAI-compatible endpoint |
| Google | Google's OpenAI compatibility endpoint |
| GoogleNative | Native Gemini v1beta GenerateContent, including conversational images |
| OpenAiImages | Standalone image generation |
| StreamingOpenAi | OpenAI chat with an explicit progressive transport |

Compatible providers accept a PSR-18 client, API key, optional ID, extra body fields and logger. Native and image providers have their own constructor options.

## A custom endpoint

With `$client` from the [shared bootstrap](getting-started.md#shared-setup), configure a Generic provider:

```php
use ByCerfrance\LlmApiLib\Provider\Generic;

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
