# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
