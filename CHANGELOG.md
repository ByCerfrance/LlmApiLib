# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `LlmInterface::supports(Capability ...$capability): bool` method to check if the LLM provider supports given
  capabilities

### Changed

- Defaults capabilities are now: `text` and `json_output`

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
