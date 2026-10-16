# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Typed provider retries, ordered single-turn failover, per-route model binding, explicit tool-free chat retries and response guards.
- Normalized provider failure categories and safe status/request/retry metadata; bounded backoff with Retry-After support and injectable waiting.
- Offline resilience example and regression tests for tool-safe retries, decorator ordering, and opaque-continuation rejection.
- Chat-owned tool execution with JSON Schema argument validation (`opis/json-schema`), configurable error policy, iteration limits, and injectable invocation registries.
- ChatTool delegation and deterministic MCP/OpenAPI tool-loop regression coverage; offline `examples/tools.php`.
- Per-turn results and token totals across all provider turns in one chat execution.

- Initial V2 chat preview with separate provider/executor contracts, immutable conversation history, request/response separation, and an OpenAI chat completions adapter.
- Optional sampling parameters, executor defaults, per-call usage with unknown metrics preserved, and provider response metadata.
- Deterministic two-exchange and conversation-branching example in `examples/chat.php`.
- Shared `OpenAICompatible` chat adapter and root-scoped payload builders for Mistral and Google compatibility options.
- Technical Model capabilities with unknown support, context/output limits, sampling constraints, and allowed parameter values; strict validation and explicitly observable parameter omission.
- Structured response formats, reasoning effort, service tier, seed, and multimodal input support on ChatRequest, plus an offline `examples/multimodal.php` example.
- OpenAI inline-PDF file mapping and normalization of individual multimodal contents into message part lists.

### Changed

- Chat request/result/response contracts expose tool options, parsed invocations, and provider turns. Custom implementations must add the corresponding methods.
- ToolCall parsing rejects malformed/non-object argument JSON and retains its original representation. Assistant messages retain text alongside tool invocations.
- `Provider\OpenAi` now implements `ChatProviderInterface` instead of `LlmInterface`. Configure it with credentials and an HTTP client, then compose it with `Chat\Chat` and `Model\Model` to execute conversations. New types use their final domain namespaces directly.
- OVH, Mistral, Google, and Generic now use the same separated provider/executor API. Generic retains its full custom endpoint configuration.
- Explicit incompatible Mistral service-tier/reasoning options and competing Google reasoning configurations now fail validation instead of silently changing behavior. Unknown Google model sampling support does not trigger blanket parameter stripping.
- `BuildContext` accepts the new provider/model contracts alongside legacy objects during migration.
- Input capability `IMAGE` is renamed to `VISION`; image generation uses the separate `IMAGE_OUTPUT` capability. Subjective/ambiguous `CODE`, `OCR`, and `MULTIMODAL` cases are removed.
- Documents no longer imply OCR capability. Known unsupported parameters fail by default instead of being silently stripped; nested schema properties are preserved.

## Older releases

The 1.x release history is archived in [CHANGELOG-1.x.md](./CHANGELOG-1.x.md).
