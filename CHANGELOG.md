# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- V2 chat with separate provider/executor contracts, immutable conversation history and request/response separation. Request, result and response contracts expose tool options, parsed invocations and provider turns, with per-turn results and token totals across all turns of one execution.
- `ChatResponse::reply()` continues a conversation with the original request options, and `getText()` returns the answer text.
- Optional sampling parameters, executor defaults, per-call usage with unknown metrics preserved, and provider response metadata.
- Structured response formats, reasoning effort, service tier, seed and multimodal input support on `ChatRequest`, including OpenAI inline-PDF file mapping and normalization of individual multimodal contents into message part lists.
- Shared `AbstractOpenAiCompatible` chat adapter and root-scoped payload builders for Mistral and Google compatibility options.
- Technical Model capabilities with unknown support, context/output limits, sampling constraints, and allowed parameter values; strict validation and explicitly observable parameter omission.
- Chat-owned tool execution with JSON Schema argument validation (`opis/json-schema`), configurable error policy, iteration limits, injectable invocation registries and ChatTool delegation.
- Typed provider retries, ordered single-turn failover, per-route model binding, explicit tool-free chat retries and response guards. Normalized provider failure categories, safe status/request/retry metadata, and bounded backoff with Retry-After support and injectable waiting.
- `FailureKind::CONTENT_REFUSED`, `FinishReason::RECITATION`, and `ProviderException::getFinishReason()`/`getProviderReason()` to identify refused or interrupted generations, for chat and images.
- Immutable usage records, nested deduplicated composites, snapshots, compact accumulation, ChatUsage tracking and provider/tool execution observation. Reported failure usage is retained, and accounting boundaries prevent combining attempts with response totals.
- Optional Model pricing with cache-sensitive token rates, explicit unit pricing/currencies, and unknown-cost propagation.
- Progressive cURL-multi chat streaming, incremental SSE decoding, fragmented tool-call assembly and final response/usage events, with explicit cancellation, pre-emission-only retries, and streaming guards/accounting.
- Opt-in progressive MCP Streamable HTTP JSON/SSE responses with strict correlation, notification callbacks, negotiated version headers and session cleanup.
- Standalone image generation through `ImageGenerator`, independent of chat, with typed retry/failover/usage decorators, normalized `ImageAsset` values and a portable `aspectRatio` translated by each provider. Ordered local reference images (`ImageAsset::fromBytes()` / `fromLocalFile()`) retain only their absolute path and MIME type until read during request preparation.
- OpenAI GPT Image generation and editing with `OpenAiImages`: base64 output, `/v1/images/generations` for text-only requests and `/v1/images/edits` for reference inputs (models supporting image input), and the `size`, `quality`, `output_format`, `output_compression`, `background`, `moderation` and `complexity` options.
- Google native v1beta image support with `GoogleNative`: conversational generation/editing, output modalities, image settings and immutable protocol-scoped continuation preserving thought signatures; standalone generation, reference-guided generation and one-shot editing through `ImageGenerator`.
- Opt-in prompt and response normalization for synchronous and streaming chats and standalone image requests, with typed interfaces, explicit input targets (including `IMAGE_PROMPT` and `IMAGE_REFERENCE`), per-content format declarations and original history/response retention.
- Configurable text cleanup and JSON minification preserving JSON string contents and number representations, including JSON inside explicitly labeled closed fences of `TextFormat::MARKDOWN` contents.
- Image resizing/conversion through optional GD and Imagick backends, automatic Imagick-first selection, EXIF orientation handling and explicit missing-extension errors on use. No image extension is required for normal library use.
- `RawJsonContent` validates already-encoded JSON without re-encoding values and supports opt-in normalization.
- Optional `TextContent` format metadata (`Content\TextFormat`, including `fromFile()`), with resolver > content > normalizer-default precedence.
- Offline examples for chat, conversation branching, multimodal input, tools, resilience, streaming, MCP over SSE, usage, normalization and images, with opt-in live image generation.

### Changed

- Namespaces reorganized by responsibility and module: `Chat\Conversation`, `Chat\Guard`, `Chat\Streaming`, `Chat\ResponseFormat`, `Content`, `Tool`, `OpenApi`, `Usage\Pricing`, and one namespace per provider (`Provider\OpenAi`, `Provider\Google`, `Provider\Mistral`, `Provider\Ovh`, `Provider\OpenAiCompatible`). The `Completion` and `Guard` root namespaces are removed. See the migration guide for the mapping.
- Module boundaries are enforced by tests: the core never depends on a provider, and providers only share the OpenAI-compatible protocol.
- `Mcp\AbstractServer` becomes `Tool\AbstractLazyToolCollection`, shared by MCP and OpenAPI.
- ToolCall parsing rejects malformed/non-object argument JSON and retains its original representation. Assistant messages retain text alongside tool invocations.
- `Provider\OpenAi\OpenAi` now implements `ChatProviderInterface` instead of `LlmInterface`. Configure it with credentials and an HTTP client, then compose it with `Chat\Chat` and `Model\Model` to execute conversations. New types use their final domain namespaces directly.
- OVH, Mistral, Google, and Generic now use the same separated provider/executor API. Generic retains its full custom endpoint configuration.
- Explicit incompatible Mistral service-tier/reasoning options and competing Google reasoning configurations now fail validation instead of silently changing behavior. Unknown Google model sampling support does not trigger blanket parameter stripping.
- `BuildContext` accepts only the new provider/model contracts. GuardException carries a ChatResponseInterface.
- Input capability `IMAGE` is renamed to `VISION`; image generation uses the separate `IMAGE_OUTPUT` capability. Subjective/ambiguous `CODE`, `OCR`, and `MULTIMODAL` cases are removed.
- Documents no longer imply OCR capability. Known unsupported parameters fail by default instead of being silently stripped; nested schema properties are preserved.
- Composite finish reasons such as `content_filter: RECITATION` parse to the new `FinishReason::RECITATION` instead of `CONTENT_FILTER`.

### Removed

- V1 Llm facade/decorators, Completion request/response inheritance, AbstractProvider, LlmTool and mutable Usage counters.
- ModelInfo, application labels, quality/cost tiers, scoring and built-in selection strategies. Applications configure ordered executors and technical Model metadata.
- `Choice` and `Choices` multiple-choice containers.
- `ProviderException` response body retention (`getBody()`, `truncateBody()`, `MAX_BODY_LENGTH`): error bodies may echo submitted content.

## Older releases

The 1.x release history is archived in [CHANGELOG-1.x.md](./CHANGELOG-1.x.md).
