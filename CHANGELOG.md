# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `ToolCollectionInterface` extracted from `ToolCollection` to allow alternative implementations (e.g. MCP servers) to provide tools via the same contract
- `PayloadBuilder` layer to decouple HTTP payload serialization from business objects (`BuilderInterface`, `PayloadBuilder`, `BuildContext`)
- Dedicated builders per object type: `CompletionBuilder`, `MessageBuilder`, `ContentBuilder`, `ToolBuilder`, `ResponseFormatBuilder`
- Provider-specific builder overrides via constructor injection (e.g. `CompletionBuilder(maxCompletionTokens: false)` for Mistral)
- `ToolCall::$additionalFields` for lossless round-trip of vendor-specific fields (e.g. Google's `extra_content.google.thought_signature`)
- Independent `PayloadReference` test helper and parity tests to validate builder output against expected payloads
- Provider-level payload mapping tests for OpenAI, Mistral, Google, OVH, and Generic

### Changed

- `CompletionInterface`, `Completion`, `CompletionResponse`: `getTools()` now returns `?ToolCollectionInterface` and `withTools()` accepts `ToolCollectionInterface|ToolInterface|null`
- `ToolCollection` now implements `ToolCollectionInterface` instead of directly implementing `Countable`, `IteratorAggregate`, `JsonSerializable`
- `ToolBuilder` now checks for `ToolCollectionInterface` instead of the concrete `ToolCollection`
- Default token key is now `max_completion_tokens` (modern OpenAI standard); Mistral overrides to legacy `max_tokens`
- `AbstractProvider::createBody()` now delegates to `PayloadBuilder` instead of relying on `Completion::jsonSerialize()`
- `ToolCall::fromArray()` now captures non-standard fields (beyond `id`, `type`, `function`) into `additionalFields`

## [1.11.0] - 2026-03-18

### Added

- `AbstractTool` abstract class to factorize common tool logic (name, description, parameters, JSON serialization)
- `LlmTool` class: a specialized tool that delegates execution to a `LlmInterface` provider, enabling agentic patterns where an orchestrator LLM can call sub-models as tools
- `LlmTool` supports two calling modes for the `promptBuilder` callable:
  - **Array mode**: `fn(array $args) => CompletionInterface`
  - **Typed mode**: `fn(string $contenu, string $format = 'json') => CompletionInterface` (arguments are filtered and passed as named parameters)
- `LlmTool::getLlm()` method to access the underlying provider for usage/cost aggregation

### Changed

- `Tool` now extends `AbstractTool` instead of directly implementing `ToolInterface` (no breaking change for consumers)

## [1.10.0] - 2026-03-17

### Added

- `ModelInfo::$maxContextTokens` optional property (`?int`, default `null`) to define the maximum context window size in tokens for a model
- `LlmInterface::getMaxContextTokens(): ?int` method to retrieve the maximum context window size, returns `null` if undefined
- `Llm::getMaxContextTokens()` returns the minimum value across all providers (conservative approach), `null` if no provider defines it

## [1.9.1] - 2026-02-25

### Added

- GitHub workflow

### Changed

- MIT license
- Public Packagist 

## [1.9.0] - 2026-02-20

### Added

- Compatibility with `berlioz/http-message` ^3.0

### Fixed

- Fix deprecated parameters with implicitly nullable via default value null

## [1.8.0] - 2026-02-05

### Added

- **Tools support (function calling)**: LLM can now call external tools/functions during inference
- `Tool` class to define a tool with name, description, JSON Schema parameters and a callback
- `ToolInterface` interface for custom tool implementations
- `ToolCall` class representing a tool call requested by the LLM
- `ToolResult` class representing the result of a tool execution
- `ToolCollection` class to manage multiple tools
- `AssistantMessage` class for assistant messages with optional tool calls
- `CompletionInterface::getTools()` and `withTools()` methods
- `CompletionInterface::getMaxToolIterations()` and `withMaxToolIterations()` methods to limit tool call loops (default: 10)
- Automatic tool execution loop in `AbstractProvider::chat()` with callback invocation

## [1.7.0] - 2026-01-29

### Changed

- Change `LlmInterface::chat()` logger parameter from `LoggerInterface` with `NullLogger` default to nullable `?LoggerInterface`

## [1.6.0] - 2026-01-29

### Added

- Add optional `LoggerInterface` parameter to `LlmInterface::chat()` for per-call logging (requests, responses, retries, failovers)

## [1.5.1] - 2025-12-08

### Fixed

- Chat with `Llm` with string content 
- Error when no LLM with capabilities is found

## [1.5.0] - 2025-12-05

### Added

- `LlmInterface::supports(Capability ...$capability): bool` method to check if the LLM provider supports given
  capabilities
- `DocumentUrlContent::fromFile()` to create a `DocumentUrlContent` from a file path or stream
- `ImageUrlContent::fromFile()` to create a `ImageUrlContent` from a file path or stream
- `ImageUrlContent::fromGdImage()` to create a `ImageUrlContent` from a `GdImage`
- `TextContent::fromFile()` to create a `TextContent` from a file path or stream
- `TextContent` accept a replacement map to replace placeholders in the text
- `ModelInfo` class to represent model information
- `CostTier` enum to represent a cost tier of model
- `QualityTier` enum to represent a quality tier of model
- `SelectionStrategy` enum to represent a selection strategy of model
- `LlmInterface::getScoring(SelectionStrategy): float` to get score for a given selection strategy
- `LlmInterface::getCost(): float` to get cost for the current provider/llm
- `CompletionInterface::getSelectionStrategy(): SelectionStrategy` to retrieve the selection strategy that the LLM must
  use

### Changed

- Defaults capabilities are now: `text` and `json_output`
- Move `ByCerfrance\LlmApiLib\Capability` to `ByCerfrance\LlmApiLib\Model\Capability`
- Move `ByCerfrance\LlmApiLib\CapabilityRequirement` to `ByCerfrance\LlmApiLib\Model\CapabilityRequirement`

## [1.4.1] - 2025-11-27

### Fixed

- `Completion::requiredCapabilities(): array` with a response format

## [1.4.0] - 2025-11-26

### Added

- `Google` LLM provider
- `LlmInterface::getCapabilities(): array` method to know which capabilities are supported by the LLM provider
- `CapabilityRequirement` interface to declare which capabilities are required by a completion/message/content

### Changed

- `Llm` class select the best provider based on capabilities

## [1.3.0] - 2025-10-16

### Added

- Parameter `$seed` in `CompletionInterface`

## [1.2.0] - 2025-10-16

### Added

- Support for **response_format** parameter in completions
- Parameter `$role` for `CompletionInterface::getLastMessage()` method, to filter last message for given role
- New `JsonContent` class to encode in JSON the content

### Changed

- `ArrayContent` now accepts `ContentInterface`, iterable, null or string values
- `InputAudio` class renamed to `InputAudioContent`
- `TextContent` accepts scalar values

## [1.1.0] - 2025-06-17

### Added

- `Retry` class to retry if a `RuntimeException` thrown

## [1.0.0] - 2025-05-21

Initial release.
