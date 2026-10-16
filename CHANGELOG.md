# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial V2 chat preview with separate provider/executor contracts, immutable conversation history, request/response separation, and an OpenAI chat completions adapter.
- Optional sampling parameters, executor defaults, per-call usage with unknown metrics preserved, and provider response metadata.
- Deterministic two-exchange and conversation-branching example in `examples/chat.php`.
- Shared `OpenAICompatible` chat adapter and root-scoped payload builders for Mistral and Google compatibility options.
- Technical Model capabilities with unknown support, context/output limits, sampling constraints, and allowed parameter values; strict validation and explicitly observable parameter omission.
- Structured response formats, reasoning effort, service tier, seed, and multimodal input support on ChatRequest, plus an offline `examples/multimodal.php` example.
- OpenAI inline-PDF file mapping and normalization of individual multimodal contents into message part lists.

### Changed

- `Provider\OpenAi` now implements `ChatProviderInterface` instead of `LlmInterface`. Configure it with credentials and an HTTP client, then compose it with `Chat\Chat` and `Model\Model` to execute conversations. New types use their final domain namespaces directly.
- OVH, Mistral, Google, and Generic now use the same separated provider/executor API. Generic retains its full custom endpoint configuration.
- Explicit incompatible Mistral service-tier/reasoning options and competing Google reasoning configurations now fail validation instead of silently changing behavior. Unknown Google model sampling support does not trigger blanket parameter stripping.
- `BuildContext` accepts the new provider/model contracts alongside legacy objects during migration.
- Input capability `IMAGE` is renamed to `VISION`; image generation uses the separate `IMAGE_OUTPUT` capability. Subjective/ambiguous `CODE`, `OCR`, and `MULTIMODAL` cases are removed.
- Documents no longer imply OCR capability. Known unsupported parameters fail by default instead of being silently stripped; nested schema properties are preserved.

## Older releases

The 1.x release history is archived in [CHANGELOG-1.x.md](./CHANGELOG-1.x.md).
