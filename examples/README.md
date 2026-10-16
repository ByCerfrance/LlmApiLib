# V2 chat preview

The `2.x` branch builds the new API directly in its final domain namespaces. OpenAI, OVH, Mistral, Google compatibility, and Generic use the separated provider/executor contracts and share `Provider\OpenAICompatible`.

Run a deterministic conversation without credentials or network traffic:

```shell
php examples/chat.php
```

The example sends two exchanges with a system prompt, then branches from the first response. Expected request history lengths are 2, 4, 4; final histories are 2 (original), 3 (first response), 5 (second response), 5 (branch).

For a real request, replace the example transport with a PSR-18 HTTP client, supply your OpenAI key to `ByCerfrance\LlmApiLib\Provider\OpenAi`, and configure an available model through `ByCerfrance\LlmApiLib\Model\Model`. The provider sends requests to OpenAI's chat completions endpoint. This example's fixture is not a live provider validation.

## Contracts available in milestone 1

- `Chat` binds the provider and model. `chat()` accepts a string or a `ChatRequestInterface`.
- `Conversation` retains ordered messages and supports immutable append. Supplied custom messages and nested content must themselves be immutable; do not insert v1's mutable `Choices` container.
- `ChatRequest` supports `maxTokens`, `temperature`, `topP`, `seed`, `responseFormat`, `reasoningEffort`, and `serviceTier`, with immutable setters and conversation replacement.
- Request values override Chat constructor defaults. Null means unspecified: inherit executor defaults, otherwise omit the field. Explicit zero is preserved for sampling options.
- `ChatResponse::toConversation()` returns the complete history, while `getResult()` exposes the final message, finish reason, provider ID, reported model, and HTTP request ID.
- `getUsage()` returns immutable `TokenUsageInterface` measurements per call. Missing provider metrics remain null, not zero. No cumulative counter is attached to Chat or the provider. The old mutable `Usage` remains for legacy infrastructure awaiting migration.
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

This slice handles a single text assistant result, including structured JSON text. Multiple output choices, generated images/audio, tool execution, retry/failover, native Google, cumulative usage, pricing, and streaming are delivered by later milestones. Unsupported tool/multimodal responses fail explicitly rather than silently discarding their contents. A requested JSON format is sent to the API; this is not a local schema-validation guarantee for its response.

Errors currently use SPL exceptions. Normalized retry categories arrive with the resilience milestone. Raw provider error bodies are not included in exception messages or logs.

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
