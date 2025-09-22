# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Support for **response_format** parameter in completions
- Parameter `$role` for `CompletionInterface::getLastMessage()` method, to filter last message for given role

### Changed

- `ArrayContent` now accepts `ContentInterface`, iterable, null or string values
- `InputAudio` class renamed to `InputAudioContent`

## [1.1.0] - 2025-06-17

### Added

- `Retry` class to retry if a `RuntimeException` thrown

## [1.0.0] - 2025-05-21

Initial release.
