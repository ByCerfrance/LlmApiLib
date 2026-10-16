# Errors and refusals

[Back to documentation](README.md)

## Goal

Find out why a chat or an image generation failed, and react without parsing error messages. Use the [shared bootstrap](getting-started.md#shared-setup).

## Two families of errors

| Exception | When | What to do |
| --- | --- | --- |
| `InvalidArgumentException` | Invalid local input, detected before any request: unsupported option, model constraint, malformed aspect ratio… | Fix the code or the configuration |
| `ProviderException` | The provider call failed, or its answer cannot be used | Read `getKind()`, then the details below |

`ProviderException` exposes typed metadata:

| Method | Content |
| --- | --- |
| `getKind()` | `FailureKind`: the category of the failure |
| `getFinishReason()` | `FinishReason` when the provider stopped the generation: `LENGTH`, `CONTENT_FILTER`, `RECITATION`… |
| `getProviderReason()` | Native code of the provider, for logs and diagnostics: `moderation_blocked`, `IMAGE_SAFETY`, `MAX_TOKENS`… |
| `getCode()` | HTTP status, when there is one |
| `getReportedUsage()` | Tokens the provider reports as consumed, when it reports them |
| `getRequestId()`, `getProviderId()` | Identify the call for support requests |
| `isTransient()` | Whether retrying the same call may succeed |

Provider error bodies are never kept, because they may repeat the submitted content: only short codes are.

## Failure kinds

| `FailureKind` | Meaning | Transient |
| --- | --- | --- |
| `TRANSPORT` | Network failure or interrupted stream | Yes |
| `RATE_LIMIT` | HTTP 429 | Yes |
| `UNAVAILABLE` | Timeout or server error (408, 500, 502, 503, 504) | Yes |
| `AUTHENTICATION` | HTTP 401 or 403 | No |
| `INVALID_REQUEST` | Other 4xx: the provider rejected the request | No |
| `CONTENT_REFUSED` | The provider refused the prompt or the generated content | No |
| `INVALID_RESPONSE` | The answer cannot be used: malformed, empty, or interrupted | No |
| `UNKNOWN` | Anything else | No |

Retries and failover only act on transient failures: a refused prompt is neither retried nor sent to the next route.

## Finish reasons

The same `FinishReason` enum describes why a generation stopped, on a response and on an exception:

| `FinishReason` | Meaning |
| --- | --- |
| `STOP` | Normal end |
| `TOOL_CALLS` | The model requested tools |
| `LENGTH` | Output truncated by the token limit |
| `CONTENT_FILTER` | Output blocked or cut by the provider's safety or policy filters |
| `RECITATION` | Output blocked because it reproduces existing content, for example copyrighted material |

`RECITATION` is the provider's own signal, not a legal assessment. Providers that do not distinguish it report `CONTENT_FILTER`.

## Chat: inspect the answer

A chat answer that stopped early is still returned when it has content, so you can decide what to do with partial text:

```php
<?php

declare(strict_types=1);

use ByCerfrance\LlmApiLib\Chat\FinishReason;

require __DIR__ . '/bootstrap.php';

$response = $chat->chat('Explain crop rotation in three sentences.');

match ($response->getResult()->getFinishReason()) {
    FinishReason::LENGTH => print('Truncated: ' . $response->getText() . PHP_EOL),
    FinishReason::CONTENT_FILTER, FinishReason::RECITATION => print('Blocked by the provider.' . PHP_EOL),
    default => print($response->getText() . PHP_EOL),
};
```

To turn these finishes into exceptions instead, wrap the chat with `FinishReasonGuardChat`: it rejects `LENGTH`, `CONTENT_FILTER` and `RECITATION` by default (see [Retries and failover](retries-and-failover.md#reject-truncated-answers)).

When the provider returns no usable answer at all, for example a blocked prompt, the chat throws a `ProviderException` of kind `CONTENT_REFUSED`.

## Images: catch the failure

A standalone image is either complete or an exception: an interrupted generation may hold a partial image, so it is never returned as a success.

```php
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\ProviderException;

try {
    $image = $generator->generate('A red barn in a green field')->getImages()[0];
} catch (ProviderException $failure) {
    if ($failure->getKind() === FailureKind::CONTENT_REFUSED) {
        // Rephrase the prompt; RECITATION means the image was too close to existing content.
        $copyright = $failure->getFinishReason() === FinishReason::RECITATION;
    }
    error_log(sprintf('Image failed: %s (%s)', $failure->getKind()->value, $failure->getProviderReason() ?? 'no code'));
    throw $failure;
}
```

`$generator` is an ImageGenerator built as in the [images guide](images.md).

| Situation | Kind | Finish reason | Example provider code |
| --- | --- | --- | --- |
| OpenAI moderation refuses the prompt | `CONTENT_REFUSED` | `CONTENT_FILTER` | `moderation_blocked`, `content_policy_violation` |
| Gemini blocks the prompt | `CONTENT_REFUSED` | `CONTENT_FILTER` | `SAFETY`, `PROHIBITED_CONTENT` |
| Gemini blocks the generated image | `CONTENT_REFUSED` | `CONTENT_FILTER` | `IMAGE_SAFETY`, `IMAGE_PROHIBITED_CONTENT` |
| Gemini detects reproduced content | `CONTENT_REFUSED` | `RECITATION` | `IMAGE_RECITATION` |
| Generation cut by the token limit | `INVALID_RESPONSE` | `LENGTH` | `MAX_TOKENS` |
| Other interruption | `INVALID_RESPONSE` | null | `NO_IMAGE`, `IMAGE_OTHER` |
| Text answer without image | `INVALID_RESPONSE` | null | null |

A text answer without image is not interpreted as a refusal: the library does not guess from the wording. Its text is not kept, as it may repeat the prompt.

Related: [Retries and failover](retries-and-failover.md), [Images](images.md).
