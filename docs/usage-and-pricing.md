# Usage and pricing

[Back to documentation](README.md)

## Goal

Count tokens across calls without confusing unknown consumption with zero. Start with the [shared bootstrap](getting-started.md#shared-setup).

## Track provider attempts

```php
<?php

declare(strict_types=1);

use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Observation\UsageObserver;
use ByCerfrance\LlmApiLib\Provider\ObservedProvider;
use ByCerfrance\LlmApiLib\Provider\RetryProvider;
use ByCerfrance\LlmApiLib\Usage\UsageCollector;

require __DIR__ . '/bootstrap.php';

$collector = new UsageCollector();
$observed = new ObservedProvider($provider, new UsageObserver($collector));
$chat = new Chat(new RetryProvider($observed), $model);

$chat->chat('Define crop rotation.');
$firstSnapshot = $collector->snapshot();
$chat->chat('Define compost.');
$currentSnapshot = $collector->snapshot();

$total = $currentSnapshot->get('total', 'token');
echo 'Attempts recorded: ' . $currentSnapshot->recordCount . PHP_EOL;
echo 'Total tokens: ' . ($total?->getValue() ?? 'unknown') . PHP_EOL;
echo 'Known subtotal: ' . ($total?->knownValue ?? 0) . PHP_EOL;
```

With no retries, two provider attempts are recorded. Actual token counts depend on the responses. `$firstSnapshot` never changes after the second call.

The observer is **inside** RetryProvider so every attempt is visible. A failed attempt with unreported usage contributes unknown measurements. If any included total is unknown, `getValue()` returns null while `knownValue` exposes the known subtotal.

## Response totals versus cumulative totals

`$response->getUsage()` covers the successful provider turns in that one Chat execution. It does not include prior exchanges or delegated ChatTool executions.

`ChatUsage($chat, $collector)` is a simpler alternative for counting returned chat responses. It cannot see intermediate failed provider attempts. Do not combine it with provider-attempt accounting in the same collector; the collector rejects mixed accounting boundaries.

To capture delegated chat consumption, observe the actual child providers with the same collector. Do not also add the parent response total.

## Estimate a price offline

This independent example makes no API calls. The prices are illustrative, not a current vendor price list.

```php
<?php

declare(strict_types=1);

use ByCerfrance\LlmApiLib\Usage\Pricing;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;

require __DIR__ . '/vendor/autoload.php';

$pricing = new Pricing('USD', input: 10, output: 20, cachedInput: 2, perTokens: 1000);
$usage = new TokenUsage(promptTokens: 1000, completionTokens: 150, totalTokens: 1150, cachedTokens: 200);
$cost = $pricing->estimate($usage);
echo $cost->amount . ' ' . $cost->currency . PHP_EOL;
```

Expected output: **11.4 USD**. The calculation is 800 ordinary input tokens (8 USD), 200 cached input tokens (0.4 USD) and 150 output tokens (3 USD).

Input tokens already include cached input. Never add cached input to input again. Unknown rates or relevant unknown quantities produce an unknown amount; explicit zero means free. These floating-point estimates are not invoice-grade decimal accounting.

## Detail or compact totals

- A default UsageCollector keeps immutable UsageRecord leaves inside a UsageComposite. Inspect them with `getDetails()->getRecords()`.
- Nested composites deduplicate leaves by ID and reject conflicting consumption for the same ID. Totals are computed from leaves, not by adding aggregate totals to their details.
- `new UsageCollector(retainDetails: false)` keeps compact totals without retained records. Feed each provider record exactly once: compact mode does not retain an unbounded deduplication set.
- Metrics are grouped by name and unit. Images, tokens and currencies remain separate. Price each model's records before combining costs across models.

Offline: [examples/usage.php](../examples/usage.php). Related: [Retries and failover](retries-and-failover.md), [Composition](composition.md).
