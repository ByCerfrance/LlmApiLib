# Retries and failover

[Back to documentation](README.md)

## Goal

Retry temporary provider failures without replaying a completed tool. Use the [shared bootstrap](getting-started.md#shared-setup).

## Retry a provider turn

```php
<?php

declare(strict_types=1);

use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Provider\RetryProvider;
use ByCerfrance\LlmApiLib\Resilience\RetryPolicy;

require __DIR__ . '/bootstrap.php';

$reliableProvider = new RetryProvider(
    $provider,
    new RetryPolicy(maxAttempts: 3, initialDelayMs: 500, multiplier: 2, maxDelayMs: 10000),
);
$chat = new Chat($reliableProvider, $model);
echo $chat->chat('Explain crop rotation briefly.')->getText() . PHP_EOL;
```

There are at most three total attempts, not three retries plus the initial call. The policy retries transport failures, rate limits and selected temporary HTTP errors. Authentication and invalid request errors are not retried.

Without Retry-After, the waits in this example are 500ms then 1000ms. A server's Retry-After minimum is honored; if it exceeds the configured maximum, execution stops rather than retrying early. The offline [resilience example](../examples/resilience.php) simulates a 429 and a 1000ms wait.

## Add an ordered fallback

This variant reuses `$provider`, `$model`, `$client` and `$apiKey` from the bootstrap. Set `FALLBACK_MODEL` to another model available on the same account:

```php
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\FailoverProvider;
use ByCerfrance\LlmApiLib\Provider\ModelProvider;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAi;

$fallback = new ModelProvider(
    new OpenAi($apiKey, $client, id: 'fallback'),
    new Model(getenv('FALLBACK_MODEL') ?: throw new RuntimeException('Set FALLBACK_MODEL.')),
);
$routes = new FailoverProvider(new RetryProvider($provider), $fallback);
$chat = new Chat($routes, $model);
$response = $chat->chat('Explain crop rotation briefly.');
echo $response->getResult()->getProviderId() . PHP_EOL;
```

The primary route exhausts its retry policy before the fallback is tried. Only transient failures trigger failover. Each new turn starts with the first route. ModelProvider binds a route to an explicit model; the library does not rank models.

Order matters: `RetryProvider(new FailoverProvider(...))` retries the entire route sequence instead. Nested policies multiply attempt budgets.

## Reject truncated answers

```php
use ByCerfrance\LlmApiLib\Chat\Guard\FinishReasonGuardChat;

$guardedChat = new FinishReasonGuardChat($chat);
$response = $guardedChat->chat('Explain crop rotation briefly.');
```

The guard rejects LENGTH and CONTENT_FILTER finishes by default. GuardException retains the rejected response for inspection. A guard does not automatically retry.

## Choose the right retry scope

Use RetryProvider for tool conversations: the failed turn already contains completed tool results. RetryChat explicitly retries whole tool-free calls and rejects tool configuration/history. Guard retries require opt-in and only work when the guard is inside that retry decorator.

Opaque native continuation cannot safely move between endpoints. Pin native Google image conversations to their original provider/model. Streaming uses a separate retry decorator that stops retrying after emission.

Related: [Tools](tools.md), [Usage and pricing](usage-and-pricing.md), [Streaming](streaming.md).
