# V2 execution examples

For step-by-step explanations, start with the [documentation index](../docs/README.md). Return to the [project README](../README.md) for installation and the API overview.

## MCP Streamable HTTP SSE

Run `php examples/mcp-sse.php` for an offline Chat → MCP tool conversation with split SSE responses.

Pass `streamingTransport: new CurlStreamingTransport()` to `Mcp\Transport\HttpStreamable` to enable progressive JSON/SSE responses. Its PSR-18 client continues to send notifications and session shutdown requests. The existing JSON-only path remains available when no streaming transport is supplied.

The progressive path supports protocol versions 2025-03-26 and 2025-06-18. Configure McpServer with the requested version; initialization validates the returned version and subsequent requests include MCP-Protocol-Version. JSON-RPC IDs are matched strictly. Interleaved notifications go to the optional `notificationHandler` callback; server-initiated requests are explicitly unsupported.

The response stream closes on the correlated result/error or any failure. Expired sessions are cleared; call `shutdown()` and explicitly initialize again. Interrupted calls are never automatically replayed. Background GET streams, Last-Event-ID reconnection/resumption and legacy HTTP+SSE endpoint discovery are not implemented. SSE framing is shared with chat; MCP messages are interpreted independently.

OpenAI, OVH, Mistral, Google compatibility, and Generic use the separated provider/executor contracts and share `Provider\OpenAiCompatible\AbstractOpenAiCompatible`.

Run a deterministic conversation without credentials or network traffic:

```shell
php examples/chat.php
```

The example sends two exchanges with a system prompt, then branches from the first response. Expected request history lengths are 2, 4, 4; final histories are 2 (original), 3 (first response), 5 (second response), 5 (branch).

For a real request, replace the example transport with a PSR-18 HTTP client, supply your OpenAI key to `ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAi`, and configure an available model through `ByCerfrance\LlmApiLib\Model\Model`. The provider sends requests to OpenAI's chat completions endpoint. This example's fixture is not a live provider validation.

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
$provider = new \ByCerfrance\LlmApiLib\Provider\Mistral\Mistral(
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
- `CompatibleChatTest` covers the production adapters without credentials. Obsolete 1.x provider fixtures have been removed.

## Current scope

Chat handles text/structured JSON responses, tools, resilience, accounting, standalone images, native Gemini conversational images and OpenAI text/tool streaming. Multiple chat choices and generated audio are not supported. Unsupported output parts fail explicitly. A requested JSON format is sent to the API; this is not a local schema-validation guarantee for its response.

Invalid local input uses SPL exceptions. Provider failures reuse ProviderException with FailureKind, HTTP status in getCode(), retry delay, provider/request IDs, and previous causes. Raw provider error bodies are never retained. Underlying transport exceptions may contain sensitive diagnostic context; decorators do not log exception chains.

## Resilience and ordering

Run `php examples/resilience.php` for an offline rate-limit recovery with a simulated wait.

- `RetryProvider` wraps a ChatProviderInterface and retries only a failed model turn. Tools already executed by Chat remain in the request history. Transport failures, HTTP 408/429 and 500/502/503/504 are transient; authentication, invalid requests, malformed responses and unknown failures are not retried automatically.
- RetryPolicy defaults to three total attempts, 500ms initial delay, multiplier 2, and a 30s maximum delay. Retry-After seconds and HTTP dates are honored. If the server's minimum delay exceeds the configured bound, retry stops rather than sending early. No sleep occurs after the final failure. RetryRunner accepts a millisecond sleeper for deterministic tests.
- `FailoverProvider($first, $second)` tries routes in application-supplied order on transient failures only. It reuses the current turn, including completed tool results. Wrap a route with `ModelProvider($provider, $model)` to bind a different model. The result reports the actual provider and reported model; no ranking or scoring is performed. Its provider ID is `failover`; use `withId()` to change it.
- Each turn starts with the first route. Opaque tool-call continuation fields require a pinned provider: multi-route failover rejects such histories before sending anything. Ordinary text/tool-result histories can cross compatible endpoints; per-route model and payload validation still applies.
- `FailoverProvider(new RetryProvider($a), new RetryProvider($b))` exhausts retries per route. `RetryProvider(new FailoverProvider($a, $b))` repeats the route sequence. Nested attempt budgets multiply; choose deliberately.
- `RetryChat` explicitly regenerates whole tool-free chat calls. It rejects configured tools and tool history; use RetryProvider for tool conversations. Wrapped custom executors must honor the tool-free contract and must not hide side effects. Partial tool-conversation failures never trigger an implicit whole-chat restart.
- `GuardChat($chat, $callback)` validates a completed response and retains it in GuardException. `FinishReasonGuardChat` rejects LENGTH and CONTENT_FILTER by default. RetryChat does not retry guard failures unless `retryOnGuard: true`; even then it does not retry responses containing tool turns.
- `RetryChat(new GuardChat(...))` can retry rejected tool-free responses when enabled. `GuardChat(new RetryChat(...))` validates only the final returned response and cannot trigger the inner retry. Explicit whole-chat retries report usage only for the successful execution; track attempts with ObservedProvider when discarded/failed consumption matters.
- These decorators expose only synchronous chat/provider contracts. They do not advertise streaming support.

## Usage, pricing and observation

Run `php examples/usage.php` for immutable cumulative snapshots and a cache-sensitive price estimate.

- UsageRecord is an immutable leaf with an ID, provider/model metadata, and Measurement values (metric name, unit, nullable value). `fromTokens()` copies measurements from TokenUsageInterface; subsequent external mutations cannot alter the record.
- UsageComposite retains nested entries, exposes deduplicated `getRecords()`, and produces immutable snapshots. Reusing a leaf in a child and its parent counts once; conflicting values for the same ID fail. Totals are computed from leaves only, never by adding an aggregate to its details.
- A UsageTotal exposes `knownValue` and `unknownCount`. `getValue()` is null if any included measurement is unknown. Missing metrics return null from snapshot lookup. Input/output/total tokens are separate metrics, not quantities to sum together; cached input is already included in input.
- UsageCollector retains detail by default. `retainDetails: false` uses compact accumulation with memory proportional to distinct metric/unit pairs and no retained history; snapshots remain immutable. Feed each provider event exactly once in compact mode: it deliberately does not retain an unbounded ID deduplication set. UsageAccumulator accepts leaves only, not aggregates.
- `ObservedProvider($provider, $observer)` emits provider.started/completed/failed events with a shared attempt ID. Place it **inside RetryProvider** and on each failover route to observe every attempt. Share a UsageObserver/collector across delegated chats to capture their actual provider consumption once.
- Failed attempts use reported standard `usage` fields when available; unreported metrics are unknown, not zero or an assumed refund. Observing outside retry sees only the overall operation and misses intermediate failures. Successful response totals still describe successful turns only.
- `ChatUsage($chat, $collector)` tracks returned turns and guard-rejected responses. It cannot see intermediate provider failures or lost partial executions: use ObservedProvider for that. Repeated tracking of the same live turn object is deduplicated. A collector rejects mixing provider-attempt accounting and chat-response accounting; choose one boundary per ledger.
- ToolExecutor accepts an observer for tool.started/completed/failed/reused. Tool events are diagnostic and are not added to token totals. Delegated provider events account for ChatTool consumption. Events omit prompts, arguments, results and raw exceptions. Observer implementations must not throw; exceptions propagate and may interrupt execution after a recorded tool result.
- Model optionally carries Pricing through `getPricing()`. Rates are application-supplied amounts per `perTokens` (default one million), with explicit currency and separate input/output/cached-input rates. Unknown is null, free is zero. Cached tokens are subtracted from ordinary input before pricing. Unknown cache counts make a differing cache price unknown; equal explicit input/cache rates do not require a cache count.
- UnitPricing handles other units such as images or seconds and rejects incompatible measurements. Cost values can be converted to Measurement for aggregation: currencies remain distinct units and are never implicitly converted. These floating-point amounts are estimates, not invoice-grade decimal accounting. Apply the actual model's rates per record before aggregating costs across models.
- Custom ModelInterface implementations must implement the new optional `getPricing()` accessor.

## Standalone and conversational images

Offline demos:

```shell
php examples/images.php
php examples/google-images.php
```

Add `--live` to opt into actual generation: OpenAI uses OPENAI_API_KEY (or OPENAI_APIKEY), Google uses GEMINI_API_KEY (or GOOGLE_APIKEY). The Google example performs four requests: generation, editing, then two independent branches. These live checks were not run during implementation; fixtures verify payloads and continuation, not model availability or visual quality.

- `ImageGenerator` binds an ImageProviderInterface and a Model, independently of ChatInterface. ImageRequest has a prompt, count and provider options; ImageResponse exposes ImageAsset values, reported token usage and provider metadata. ImageAsset preserves MIME/base64 data or a URL reference without downloading it.
- `OpenAiImages` calls `/v1/images/generations`. GPT Image models return base64 and reject the legacy response_format option. Explicit size, quality, output format, compression, background and moderation settings are supported subject to the actual model. Model unsupported fields/allowed values are checked. Editing/variation endpoints are not implemented.
- RetryImageGenerator and FailoverImageGenerator reuse bounded retry rules and supplied fallback order. Place ImageUsage inside retries/on each route to retain attempt-level consumption; missing failed consumption is unknown. Image counts and tokens remain distinct metrics. Use a separate image collector, then combine its detail with other ledgers through UsageComposite.
- `GoogleNative` calls **v1beta/models/{model}:generateContent** with x-goog-api-key authentication. `Google` still means the separate OpenAI compatibility endpoint. The configured model remains `gemini-3.1-flash-image` for Nano Banana 2; no compatibility-endpoint parity is assumed.
- Request `outputModalities: [OutputModality::TEXT, OutputModality::IMAGE]` (or IMAGE alone) and optional `imageConfig: ['aspectRatio' => '16:9', 'imageSize' => '1K']`. ChatRequest immutable setters and continuation retain these fields. OpenAI-compatible chat rejects image output rather than pretending to support it.
- Native responses preserve ordered visible text/image parts as ArrayContent; ImageContent exposes its ImageAsset. ProviderAssistantMessage also retains the complete native content, including thought signatures and internal thought parts, for exact replay. Internal thought parts are excluded from visible normalized output. Retain the returned conversation for edits/forks; reconstructing only visible content discards continuation metadata.
- Native history is scoped to the same configured provider ID and model. Cross-model/protocol continuation and multi-route failover of native history are rejected. Independent branches copy the immutable history and retain their own later results.
- Native text, inline image/audio/document inputs, JSON formats and function tools are mapped explicitly. Media input must use base64 data URIs (or ImageContent with inline data); arbitrary remote URLs are not fetched. Returned native file references survive replay. Unsupported native parts and incompatible options fail explicitly.
- Native serviceTier, reasoningEffort and parallelToolCalls mappings are not enabled; configure native thinking options through GoogleNative generationConfig instead. Core request fields cannot be overridden through that configuration. Model constraints remain enforced. Upload APIs, Interactions API and automatic CONTINUATION-token resumption are not implemented.
- Custom ChatRequestInterface implementations must add output modality/image configuration accessors.

References: [Gemini GenerateContent API](https://ai.google.dev/api/generate-content), [Nano Banana models](https://ai.google.dev/gemini-api/docs/image-generation), [OpenAI image API](https://developers.openai.com/api/reference/resources/images).

## Progressive chat streaming

Run `php examples/streaming.php` offline, or add `--live` with an OpenAI key and optional OPENAI_MODEL.

- StreamingOpenAi uses a dedicated StreamingTransportInterface for `stream()` and retains a PSR-18 client for ordinary `complete()`. CurlStreamingTransport pumps cURL multi progressively; PSR-18 alone does not imply unbuffered delivery. The local HTTP regression server waits for client acknowledgement of the first fragment before generating the next one.
- StreamingChat implements StreamingChatInterface in addition to synchronous ChatInterface. `stream()` returns a single-use ChatStream, yielding text.delta, tool.delta, tool.started, tool.completed and one completed event carrying the assembled ChatResponse. The final response retains all tool rounds, history and nullable usage.
- Always use `try/finally { $stream->close(); }`, especially when breaking out of iteration early. Close cancels the active HTTP response immediately; it prevents further tool execution and provider turns. Already completed tool effects cannot be cancelled. A cancelled stream does not fabricate a completed response.
- SSE parsing handles arbitrary network splits, UTF-8 byte boundaries, CR/LF/CRLF, comments, multiline data and multiple events per chunk. Incomplete frames are not dispatched. A streamed OpenAI turn must finish with a finish reason and `[DONE]`; interruption is explicit.
- Tool IDs/names/JSON argument fragments are assembled and validated before execution. Deltas are informational, not executable calls. Usage can arrive in the final usage-only chunk and remains unknown when omitted.
- RetryStreamingProvider may retry a transient failure only before the first event of the current turn. Once a text/tool event is emitted, it propagates failure without replay. Completed tools from prior turns remain in the request. Streaming failover is not provided.
- StreamingGuardChat checks the assembled final response; previously emitted text cannot be retracted. StreamingChatUsage records completed responses; place it inside a streaming guard to retain rejected response usage. Cancelled/partial streams may have unreported billed consumption and do not contribute invented zero measurements.
- Ordinary RetryProvider, FailoverProvider, ChatUsage and GuardChat remain synchronous and do not claim streaming support. Native Gemini image streaming and standalone image streaming are not enabled by these interfaces.

## Tool conversations

Run `php examples/tools.php` for an offline tool conversation.

- Configure `ChatRequest(tools: $collection)` using ToolCollection, filtered/composite collections, McpServer, or OpenApi. Chat owns the execution loop; providers return one turn with parsed ToolCall objects.
- Arguments are validated with `opis/json-schema` before execution. Original invocation JSON preserves nested object/array distinctions. Malformed JSON and duplicate IDs in one response fail before any tool executes.
- `ToolErrorPolicy::STOP` is the default. `RETURN_ERROR` sends a structured error result for unknown tools, invalid arguments, or execution failures. Invalid schema configuration still stops execution; raw exception details are not sent to the model.
- `maxToolIterations` limits execution rounds (default 10, zero disables execution). Several calls in one round execute sequentially, even when `parallelToolCalls` permits the model to request them together. After a required tool round, tool choice becomes AUTO so the model can finish.
- Completed results are recorded before the next provider turn. Repeated IDs with the same tool/arguments reuse their result; new IDs can invoke the same tool again. Conflicting ID reuse fails. The default registry belongs to one chat execution and is reconstructed from completed conversation history on continuation/forks.
- Inject `Chat(registryFactory: fn() => $registry)` to retain results for explicit recovery. Scope a shared registry to one logical conversation: unrelated conversations may reuse provider-generated IDs. A failure under STOP is not recorded as a completed result. Rebuilding a chat from scratch with a new registry does not retain lost results.
- The registry does not guarantee exactly-once execution across crashes, concurrent workers, or side effects completed before an exception. Those require tool-side idempotency or transactions.
- `ChatTool` delegates to a ChatInterface, with array or named-argument prompt builders. It replaces the removed LlmTool API.
- Custom ChatRequestInterface, ChatResponseInterface, and ChatResultInterface implementations must implement the new tool settings, turn list, and invocation accessors.

## Model constraints and multimodal requests

Run `php examples/multimodal.php` for a local image-input/JSON-schema example. It prints the exact serialized request without network traffic.

- `capabilities: null` (default) means unknown. An explicit list is exhaustive: listed capabilities are supported, absent ones are unsupported, and `[]` supports none. `supports()` returns `true`, `false`, or `null`.
- `VISION` describes image input. `IMAGE_OUTPUT` describes image generation. `CODE`, `OCR`, and `MULTIMODAL` are removed; use actual input/output capabilities rather than quality judgments. Documents require `DOCUMENT`, not OCR.
- `maxContextTokens` is descriptive metadata: this library does not pretend to count tokens without a tokenizer. A configured `maxOutputTokens` is enforced against requested limits, including extensions.
- `tunable: false` rejects explicit sampling parameters, including seed and top-k extensions. `null` means unknown; `true` does not waive other parameter constraints.
- `unsupportedParameters` replaces implicit `stripFields` behavior. Root fields and generation-option paths such as `response_format.json_schema.strict` are supported; targeting model/history/tools or schema contents is rejected.
- `parameterValues` lists exact allowed scalar values, e.g. `['reasoning_effort' => ['high', 'none']]`. Incompatible explicit values are never automatically downgraded.
- Providers default to `ParameterPolicy::STRICT`. Explicit `OMIT_UNSUPPORTED` removes incompatible parameters and requires an injected logger to report each omission. Missing content capabilities and output-size violations remain errors in either mode.
- Existing ImageUrlContent, InputAudioContent, DocumentUrlContent, ArrayContent, and response-format objects are reused. Protocol support still depends on the actual endpoint/model; capability declarations are caller-supplied facts, not a built-in vendor catalog.
- OpenAI chat PDF input maps inline `data:application/pdf;base64,...` documents to file parts. Remote document URLs are rejected for this adapter: load a PDF using `DocumentUrlContent::fromFile()` first. Other compatible adapters retain the existing document_url representation.
