# V2 chat preview

The `2.x` branch builds the new API directly in its final domain namespaces. OpenAI, OVH, Mistral, Google compatibility, and Generic use the separated provider/executor contracts and share `Provider\OpenAICompatible`.

Run a deterministic conversation without credentials or network traffic:

```shell
php examples/chat.php
```

The example sends two exchanges with a system prompt, then branches from the first response. Expected request history lengths are 2, 4, 4; final histories are 2 (original), 3 (first response), 5 (second response), 5 (branch).

For a real request, replace the example transport with a PSR-18 HTTP client, supply your OpenAI key to `ByCerfrance\LlmApiLib\Provider\OpenAi`, and configure an available model through `ByCerfrance\LlmApiLib\Model\Model`. The provider sends requests to OpenAI's chat completions endpoint. This example's fixture is not a live provider validation.

## Available contracts

- `Chat` binds the provider and model. `chat()` accepts a string or a `ChatRequestInterface`.
- `Conversation` retains ordered messages and supports immutable append. Supplied custom messages and nested content must themselves be immutable; do not insert v1's mutable `Choices` container.
- `ChatRequest` supports `maxTokens`, `temperature`, `topP`, `seed`, `responseFormat`, `reasoningEffort`, and `serviceTier`, with immutable setters and conversation replacement.
- Request values override Chat constructor defaults. Null means unspecified: inherit executor defaults, otherwise omit the field. Explicit zero is preserved for sampling options.
- `ChatResponse::toConversation()` returns the complete history, while `getResult()` exposes the final message, finish reason, provider ID, reported model, and HTTP request ID.
- `getUsage()` sums all provider turns in this chat call; `getTurns()` exposes their individual results. Missing metrics remain null, not zero. These totals do not include earlier conversation calls or delegated ChatTool usage. No cumulative counter is attached to Chat or the provider.
- Provider extensions cannot replace the model/history, enable streaming/tools/multiple choices, or overwrite configured fields.

## Provider configuration

All built-in providers accept `apiKey`, a PSR-18 `client`, optional `id`, `extraBody`, and `logger`. Generic additionally takes a full `uri` (string or PSR-7 URI), not a base URL. The model belongs to `Chat`, not the provider:

The offline example also accepts a provider name, e.g. `php examples/chat.php mistral`. Available values: `openai` (default), `mistral`, `google`, `ovh`, and `generic`. All use the same local transport fixture.

```php
$provider = new \ByCerfrance\LlmApiLib\Provider\Mistral(
    apiKey: $apiKey,
    client: $client,
);
$chat = new \ByCerfrance\LlmApiLib\Chat\Chat(
    provider: $provider,
    model: new \ByCerfrance\LlmApiLib\Model\Model('your-model-id'),
);
```

- Mistral maps `max_completion_tokens` to `max_tokens`. Explicit `service_tier` and reasoning efforts other than `high`/`none` are rejected rather than silently removed or reduced. Do not configure both maximum-token spellings.
- Google uses its OpenAI compatibility endpoint. Unspecified sampling options are omitted; explicit options for models with unknown capabilities are preserved, including those in `extraBody`. Known constraints configured in Model are enforced. Competing `reasoning_effort` and Google thinking level/budget are rejected.
- Payload builders receive the configured provider and selected Model in BuildContext. Root adaptations use ChatPayload so nested schema fields are preserved.
- Existing 1.x provider tests use test-only fixtures while the old tool/selection infrastructure is migrated. `CompatibleChatTest` covers the actual production adapters without credentials.

## Current scope

This slice handles text/structured JSON responses, function-calling conversations, and resilience decorators. Multiple output choices, generated images/audio, native Google, cumulative usage, pricing, and streaming are delivered by later milestones. Unsupported multimodal responses fail explicitly rather than silently discarding their contents. A requested JSON format is sent to the API; this is not a local schema-validation guarantee for its response.

Invalid local input uses SPL exceptions. Provider failures reuse ProviderException with FailureKind, HTTP status in getCode(), retry delay, provider/request IDs, and previous causes. New adapters never retain raw error bodies. Underlying transport exceptions may contain sensitive diagnostic context; decorators do not log exception chains.

## Resilience and ordering

Run `php examples/resilience.php` for an offline rate-limit recovery with a simulated wait.

- `RetryProvider` wraps a ChatProviderInterface and retries only a failed model turn. Tools already executed by Chat remain in the request history. Transport failures, HTTP 408/429 and 500/502/503/504 are transient; authentication, invalid requests, malformed responses and unknown failures are not retried automatically.
- RetryPolicy defaults to three total attempts, 500ms initial delay, multiplier 2, and a 30s maximum delay. Retry-After seconds and HTTP dates are honored. If the server's minimum delay exceeds the configured bound, retry stops rather than sending early. No sleep occurs after the final failure. RetryRunner accepts a millisecond sleeper for deterministic tests.
- `FailoverProvider([$first, $second])` tries routes in application-supplied order on transient failures only. It reuses the current turn, including completed tool results. Wrap a route with `ModelProvider($provider, $model)` to bind a different model. The result reports the actual provider and reported model; no ranking or scoring is performed.
- Each turn starts with the first route. Opaque tool-call continuation fields require a pinned provider: multi-route failover rejects such histories before sending anything. Ordinary text/tool-result histories can cross compatible endpoints; per-route model and payload validation still applies.
- `FailoverProvider([new RetryProvider($a), new RetryProvider($b)])` exhausts retries per route. `RetryProvider(new FailoverProvider([$a, $b]))` repeats the route sequence. Nested attempt budgets multiply; choose deliberately.
- `RetryChat` explicitly regenerates whole tool-free chat calls. It rejects configured tools and tool history; use RetryProvider for tool conversations. Wrapped custom executors must honor the tool-free contract and must not hide side effects. Partial tool-conversation failures never trigger an implicit whole-chat restart.
- `GuardChat($chat, $callback)` validates a completed response and retains it in GuardException. `FinishReasonGuardChat` rejects LENGTH and CONTENT_FILTER by default. RetryChat does not retry guard failures unless `retryOnGuard: true`; even then it does not retry responses containing tool turns.
- `RetryChat(new GuardChat(...))` can retry rejected tool-free responses when enabled. `GuardChat(new RetryChat(...))` validates only the final returned response and cannot trigger the inner retry. Explicit whole-chat retries report usage only for the successful execution; discarded/failed attempts require later tracking decorators.
- These decorators expose only synchronous chat/provider contracts. They do not advertise streaming support.

## Tool conversations

Run `php examples/tools.php` for an offline tool conversation.

- Configure `ChatRequest(tools: $collection)` using ToolCollection, filtered/composite collections, McpServer, or OpenApi. Chat owns the execution loop; providers return one turn with parsed ToolCall objects.
- Arguments are validated with `opis/json-schema` before execution. Original invocation JSON preserves nested object/array distinctions. Malformed JSON and duplicate IDs in one response fail before any tool executes.
- `ToolErrorPolicy::STOP` is the default. `RETURN_ERROR` sends a structured error result for unknown tools, invalid arguments, or execution failures. Invalid schema configuration still stops execution; raw exception details are not sent to the model.
- `maxToolIterations` limits execution rounds (default 10, zero disables execution). Several calls in one round execute sequentially, even when `parallelToolCalls` permits the model to request them together. After a required tool round, tool choice becomes AUTO so the model can finish.
- Completed results are recorded before the next provider turn. Repeated IDs with the same tool/arguments reuse their result; new IDs can invoke the same tool again. Conflicting ID reuse fails. The default registry belongs to one chat execution and is reconstructed from completed conversation history on continuation/forks.
- Inject `Chat(registryFactory: fn() => $registry)` to retain results for explicit recovery. Scope a shared registry to one logical conversation: unrelated conversations may reuse provider-generated IDs. A failure under STOP is not recorded as a completed result. Rebuilding a chat from scratch with a new registry does not retain lost results.
- The registry does not guarantee exactly-once execution across crashes, concurrent workers, or side effects completed before an exception. Those require tool-side idempotency or transactions.
- `ChatTool` delegates to a ChatInterface, with array or named-argument prompt builders. It replaces LlmTool for the new API; legacy LlmTool remains for legacy consumers.
- Custom ChatRequestInterface, ChatResponseInterface, and ChatResultInterface implementations must implement the new tool settings, turn list, and invocation accessors.

## Model constraints and multimodal requests

Run `php examples/multimodal.php` for a local image-input/JSON-schema example. It prints the exact serialized request without network traffic.

- `capabilities: null` (default) means unknown. An explicit list is exhaustive: listed capabilities are supported, absent ones are unsupported, and `[]` supports none. `supports()` returns `true`, `false`, or `null`.
- `VISION` describes image input. `IMAGE_OUTPUT` is separate and prepares future image generation. `CODE`, `OCR`, and `MULTIMODAL` are removed; use actual input/output capabilities rather than quality judgments. Documents require `DOCUMENT`, not OCR.
- `maxContextTokens` is descriptive metadata: this library does not pretend to count tokens without a tokenizer. A configured `maxOutputTokens` is enforced against requested limits, including extensions.
- `tunable: false` rejects explicit sampling parameters, including seed and top-k extensions. `null` means unknown; `true` does not waive other parameter constraints.
- `unsupportedParameters` replaces implicit `stripFields` behavior. Root fields and generation-option paths such as `response_format.json_schema.strict` are supported; targeting model/history/tools or schema contents is rejected.
- `parameterValues` lists exact allowed scalar values, e.g. `['reasoning_effort' => ['high', 'none']]`. Incompatible explicit values are never automatically downgraded.
- Providers default to `ParameterPolicy::STRICT`. Explicit `OMIT_UNSUPPORTED` removes incompatible parameters and requires an injected logger to report each omission. Missing content capabilities and output-size violations remain errors in either mode.
- Existing ImageUrlContent, InputAudioContent, DocumentUrlContent, ArrayContent, and response-format objects are reused. Protocol support still depends on the actual endpoint/model; capability declarations are caller-supplied facts, not a built-in vendor catalog.
- OpenAI chat PDF input maps inline `data:application/pdf;base64,...` documents to file parts. Remote document URLs are rejected for this adapter: load a PDF using `DocumentUrlContent::fromFile()` first. Other compatible adapters retain the existing document_url representation.
