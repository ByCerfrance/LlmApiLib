# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Provider labels: `LlmInterface::getLabels()` to tag providers with arbitrary labels (e.g. `summarize`, `classification`)
- `AbstractProvider` and `Generic` accept a `labels` parameter in their constructor
- `LlmDecoratorTrait` propagates labels from the inner provider (`Retry`, `Guard`, etc.)
- `CompletionInterface::getLabels()` and `withLabels(array $labels)` to require specific labels for provider filtering
- `Llm::filterByLabels(array $labels, bool $matchAll = true)` to filter providers by labels (AND or OR logic), returns a new `Llm` instance
- `Llm::filterByCapabilities(Capability ...)` to filter providers by capabilities, returns a new `Llm` instance
- `Llm::sortByStrategy(?SelectionStrategy)` to sort providers by scoring strategy, returns a new `Llm` instance
- `Llm` implements `IteratorAggregate` and `Countable` for direct iteration and counting of providers

### Changed

- `Llm::chat()` uses `filterByLabels()`, `filterByCapabilities()`, and `sortByStrategy()` internally

### Deprecated

- `Llm::getProviders()`: use `foreach ($llm as $provider)` or `count($llm)` instead

## [1.15.0] - 2026-04-30

### Changed

- `AbstractProvider::chat()` now silently strips `reasoning_effort` from the payload when the model does not declare `Capability::REASONING`, and emits a `warning`-level log

### Fixed

- `JsonSchemaFormat` now strips the `$schema` JSON Schema keyword from the schema before serialization, as it is rejected by OpenAI and Gemini in the `response_format` context

## [1.14.1] - 2026-04-29

### Fixed

- Memory leak in `ImageUrlContent::gdImageToBase64()`: the resized `GdImage` returned by `b_img_resize()` was never destroyed, leaking GD memory on every resize operation

## [1.14.0] - 2026-04-28

### Added

- `AbstractProvider::$extraBody` constructor parameter (`array`, default `[]`) to inject vendor-specific keys at the root of the request payload, similar to the Python OpenAI SDK `extra_body` concept
- `AbstractProvider::getExtraBody()` to retrieve the configured extra body parameters
- `Generic` provider now accepts and propagates the `$extraBody` parameter
- `UsageInterface::getCachedTokens()` to expose cached prompt tokens reported by providers (OpenAI, Mistral, Google)
- `Usage` now tracks `cachedTokens` from `prompt_tokens_details.cached_tokens` in provider responses
- Cached tokens count is included in structured logger context (`cached_tokens` key)
- `FinishReason::parse()` static method to handle composite finish reason formats (e.g. `content_filter: RECITATION` from Google Gemini)

### Fixed

- `FinishReasonGuard` now correctly rejects responses with composite `finish_reason` values (e.g. `content_filter: RECITATION`) that were previously silently passing through as `null`

## [1.13.0] - 2026-04-28

### Added

- `ServiceTier` enum (`auto`, `default`, `flex`, `priority`) with `fallback()` chain for provider compatibility
- `CompletionInterface::getServiceTier()` and `withServiceTier()` to control API processing priority and pricing tier
- `ReasoningEffort` enum (`none`, `low`, `medium`, `high`, `xhigh`) with `fallback()` chain for provider compatibility
- `CompletionInterface::getReasoningEffort()` and `withReasoningEffort()` to control reasoning depth on supported models
- Setting `reasoningEffort` automatically adds `Capability::REASONING` to required capabilities
- `CompletionInterface::getParallelToolCalls()` and `withParallelToolCalls()` to control whether tool calls can be executed in parallel (`?bool`, default `null` = not sent)
- `ToolChoice` enum (`auto`, `none`, `required`) to control tool usage strategy
- `CompletionInterface::getToolChoice()` and `withToolChoice()` to set the tool choice strategy
- `ModelInfo::$maxOutputTokens` optional property (`?int`, default `null`) to define the maximum number of output tokens a model can generate
- `LlmInterface::getMaxOutputTokens(): ?int` method to retrieve the maximum output tokens, returns `null` if undefined
- `Llm::getMaxOutputTokens()` returns the minimum value across all providers (conservative approach), `null` if no provider defines it

### Changed

- `MistralCompletionBuilder` now strips `service_tier` from payload (unsupported by Mistral)
- `MistralCompletionBuilder` now falls back `reasoning_effort` to supported values (`high`, `none`) using the fallback chain
- `MistralCompletionBuilder` now remaps `tool_choice` value `required` to `any` (Mistral naming convention)

## [1.12.0] - 2026-03-31

### Added

- `ToolCollectionInterface` extracted from `ToolCollection` for alternative implementations
- `McpServer`: MCP client (spec 2025-03-26) with transport abstraction via `TransportInterface`
- `HttpStreamable`: HTTP Streamable transport for MCP (JSON-only)
- `McpTool`: tool value object for MCP-discovered tools
- `OpenApi`: OpenAPI client that discovers tools from a spec and executes REST calls (requires
  `devizzent/cebe-php-openapi`)
- `OpenApiTool`: tool value object for OpenAPI operations with REST metadata
- `FilteredToolCollection`: decorator to filter any `ToolCollectionInterface` with include/exclude patterns
- `AbstractServer`: shared base for remote tool providers (MCP, OpenAPI) with lazy-init
- `ToolCall::$additionalFields` for vendor-specific round-trip (e.g. Google)
- `MistralCompletionBuilder`: provider-specific builder that renames `max_completion_tokens` to `max_tokens`
- `FinishReason` enum and `CompletionResponseInterface::getFinishReason()` to detect truncated or filtered responses
- `Choice` class wrapping a message with its finish reason; `Choices` now contains `Choice[]` with
  `getPreferredChoice()`
- `UserMessage`, `SystemMessage` classes and `MessageFactory` for typed message creation from API responses
- Guard system: `Guard` (callable decorator), `FinishReasonGuard` (rejects `LENGTH`/`CONTENT_FILTER`),
  `GuardException` (carries rejected response)
- `LlmDecoratorTrait` with `getProvider()` to factorize single-provider `LlmInterface` decorators
- `Retry::$multiplier` for exponential backoff and `Retry::$retryOnGuard` to skip retries on guard failures

### Changed

- `getTools()` returns `?ToolCollectionInterface`, `withTools()` accepts `ToolCollectionInterface|ToolInterface|null`
- `Completion::jsonSerialize()` now uses `max_completion_tokens` (OpenAI standard); was `max_tokens`
- `AssistantMessage::jsonSerialize()` omits `content` key when tool calls are present
- `AbstractProvider::createBody()` delegates to `PayloadBuilder`
- **`PayloadBuilder` refactored**: `jsonSerialize()` is now the single source of truth for the OpenAI-compatible wire
  format; `PayloadBuilder` recursively resolves `JsonSerializable` objects and dispatches to provider-specific builders
  only when needed; removed 5 default builders (`CompletionBuilder`, `MessageBuilder`, `ContentBuilder`, `ToolBuilder`,
  `ResponseFormatBuilder`) in favor of automatic `JsonSerializable` fallback
- Message hierarchy: `AssistantMessage` extends `Message`, content accepts `null` (**breaking**: `Choices` constructor
  takes `Choice[]`)
- `AbstractProvider::chat()` uses `MessageFactory` and `FinishReason` enum for response parsing

## [1.11.0] - 2026-03-18

### Added

- `AbstractTool` abstract class to factorize common tool logic (name, description, parameters, JSON serialization)
- `LlmTool` class: a specialized tool that delegates execution to a `LlmInterface` provider, enabling agentic patterns
  where an orchestrator LLM can call sub-models as tools
- `LlmTool` supports two calling modes for the `promptBuilder` callable:
    - **Array mode**: `fn(array $args) => CompletionInterface`
    - **Typed mode**: `fn(string $contenu, string $format = 'json') => CompletionInterface` (arguments are filtered and
      passed as named parameters)
- `LlmTool::getLlm()` method to access the underlying provider for usage/cost aggregation

### Changed

- `Tool` now extends `AbstractTool` instead of directly implementing `ToolInterface` (no breaking change for consumers)

## [1.10.0] - 2026-03-17

### Added

- `ModelInfo::$maxContextTokens` optional property (`?int`, default `null`) to define the maximum context window size in
  tokens for a model
- `LlmInterface::getMaxContextTokens(): ?int` method to retrieve the maximum context window size, returns `null` if
  undefined
- `Llm::getMaxContextTokens()` returns the minimum value across all providers (conservative approach), `null` if no
  provider defines it

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
- `CompletionInterface::getMaxToolIterations()` and `withMaxToolIterations()` methods to limit tool call loops (default:
  10)
- Automatic tool execution loop in `AbstractProvider::chat()` with callback invocation

## [1.7.0] - 2026-01-29

### Changed

- Change `LlmInterface::chat()` logger parameter from `LoggerInterface` with `NullLogger` default to nullable
  `?LoggerInterface`

## [1.6.0] - 2026-01-29

### Added

- Add optional `LoggerInterface` parameter to `LlmInterface::chat()` for per-call logging (requests, responses, retries,
  failovers)

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
