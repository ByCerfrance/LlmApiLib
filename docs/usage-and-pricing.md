# Usage and pricing

[Back to documentation](README.md)

## Goal

Count tokens across calls without confusing unknown consumption with zero. Start with the [shared bootstrap](getting-started.md#shared-setup).

## Portable token accounting

`TokenUsageInterface` exposes the same accounting contract across providers:

| Method | Meaning |
| --- | --- |
| `getPromptTokens()` | Input tokens, including cached input. |
| `getCompletionTokens()` | Output tokens, including billable reasoning exactly once. |
| `getTotalTokens()` | The provider-reported total; it can include categories beyond portable input and output. |
| `getCachedTokens()` | Cache-read tokens, already included in input; not additional input or cache-write tokens. |

Each counter is independently nullable. `null` means unknown, not zero; `0` means an established zero count. A successful response does not establish that missing usage is zero. The same distinction applies to reported failure usage and aggregated usage.

Adapters must normalize their provider's counters to this contract. OpenAI Chat Completions already includes reasoning in `completion_tokens`: its `completion_tokens_details.reasoning_tokens` is a breakdown, not an extra charge to add. Gemini reports candidates and thinking separately, so its normalization must include both when known. Do not infer thinking from total minus input: Gemini totals can also include tool-use prompt tokens.

An omitted cache counter is not universally equivalent to a cache miss. Only documented provider semantics can justify normalizing omission to zero; an OpenAI-compatible endpoint does not automatically inherit every OpenAI reporting guarantee. Pricing assumptions must not overwrite unknown observed counters.

`GoogleNative` adds explicitly reported `thoughtsTokenCount` to `candidatesTokenCount`. When thinking is omitted, it retains the reported candidate count; an explicitly unknown thinking count or an unknown candidate count makes portable output unknown. The provider-reported total is preserved without reconstruction. An omitted `cachedContentTokenCount` remains unknown. OpenAI chat likewise preserves missing cache details as unknown; the standalone OpenAI Images usage schema does not report a cache-read count, so its portable cache count remains unknown too.

This contract does not make a single input/output tariff sufficient for every provider product. Modality-specific rates, cache writes, storage and tool fees may require separate accounting. `Pricing` estimates the token categories and rates supplied by the application, not a complete provider invoice.

References: [OpenAI reasoning](https://platform.openai.com/docs/guides/reasoning), [OpenAI prompt caching](https://platform.openai.com/docs/guides/prompt-caching), [Gemini usage metadata](https://ai.google.dev/api/generate-content#UsageMetadata).

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

use ByCerfrance\LlmApiLib\Usage\Pricing\Pricing;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;

require __DIR__ . '/vendor/autoload.php';

$pricing = new Pricing('USD', input: 10, output: 20, cachedInput: 2, perTokens: 1000);
$usage = new TokenUsage(promptTokens: 1000, completionTokens: 150, totalTokens: 1150, cachedTokens: 200);
$cost = $pricing->estimate($usage);
echo $cost->amount . ' ' . $cost->currency . PHP_EOL;
```

Expected output: **11.4 USD**. The calculation is 800 ordinary input tokens (8 USD), 200 cached input tokens (0.4 USD) and 150 output tokens (3 USD).

Input tokens already include cached input. Never add cached input to input again. Unknown rates or relevant unknown quantities produce an unknown amount; explicit zero means free. These floating-point estimates are not invoice-grade decimal accounting.

### Reference cost without cache discounts

For a provider-independent reference cost without cache discounts, set `cachedInput` to the same rate as `input`. The input count already includes cache reads, so all input tokens receive the ordinary rate, whether the cache count is known or unknown:

```php
$pricing = new Pricing('USD', input: 1, output: 2, cachedInput: 1);
$usage = new TokenUsage(promptTokens: 2124, completionTokens: 241, totalTokens: 2365);
$cost = $pricing->estimate($usage);
// $cost->amount is approximately 0.002606 USD; $usage->getCachedTokens() is still null.
```

This deliberately ignores even explicitly reported cache hits without changing observed usage. Use a separate `Pricing` with the actual cached-input rate when estimating discounted charges. If the two input rates differ and the cache count is unknown, the discounted estimate remains unknown. Leaving `cachedInput` unset means its rate is unknown, not that cache discounts are disabled.

Unknown input/output counts or required rates still produce an unknown amount, including after failures or timeouts. A missing total does not prevent pricing known input and output. Do not silently skip unknown costs when enforcing a spending limit; treat them as unresolved consumption.

For a tariff where cached reads are cheaper than ordinary input, the reference cost is conservative with respect to cache-read discounts only. It is not a universal upper bound: higher cache rates, cache-write charges, modality-specific rates, storage and tool fees are outside that guarantee. Supply the appropriate rates for the model and applied service tier.

## Detail or compact totals

- A default UsageCollector keeps immutable UsageRecord leaves inside a UsageComposite. Inspect them with `getDetails()->getRecords()`.
- Nested composites deduplicate leaves by ID and reject conflicting consumption for the same ID. Totals are computed from leaves, not by adding aggregate totals to their details.
- `new UsageCollector(retainDetails: false)` keeps compact totals without retained records. Feed each provider record exactly once: compact mode does not retain an unbounded deduplication set.
- Metrics are grouped by name and unit. Images, tokens and currencies remain separate. Price each model's records before combining costs across models.

Offline: [examples/usage.php](../examples/usage.php). Related: [Retries and failover](retries-and-failover.md), [Tools](tools.md).
