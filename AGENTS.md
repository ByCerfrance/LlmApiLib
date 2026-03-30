# AGENTS.md

## Project Overview

`bycerfrance/llm-api-lib` is a PHP 8.3+ library for interacting with multiple LLM providers
(Google, Mistral, OpenAI, OVH, and any OpenAI-compatible endpoint) with failover, retry,
tool calling, and MCP client support. It is framework-agnostic, relying on PSR-7/PSR-18 interfaces.

## Build / Test / Lint Commands

```bash
# Install dependencies
composer install

# Run full test suite
composer run test

# Run a single test class
vendor/bin/phpunit tests/Completion/CompletionTest.php

# Run a single test method
vendor/bin/phpunit --filter testMethodName

# Run a single test method within a specific class
vendor/bin/phpunit --filter 'CompletionTest::testMethodName'

# Run tests with coverage output
vendor/bin/phpunit --coverage-text

# Run static analysis (PHPStan)
composer run analyse
```

PHPUnit is configured via `phpunit.xml.dist`. The test suite is strict: it enforces coverage
metadata (`requireCoverageMetadata`), fails on risky tests, warnings, and PHPUnit deprecations.

PHPStan runs against both `src/` and `tests/` with default configuration (no `phpstan.neon`).

## Code Style Guidelines

### File Structure

Every PHP file must follow this exact structure:

1. `<?php` opening tag
2. Blank line
3. `declare(strict_types=1);` -- **mandatory in every file**
4. Blank line
5. `namespace` declaration
6. Blank line
7. `use` imports (alphabetically sorted, single block, no group separators)
8. Blank line
9. Class/interface/enum/trait declaration

One class/interface/enum/trait per file, following PSR-4 autoloading.

### Naming Conventions

| Element         | Convention                  | Example                              |
|-----------------|-----------------------------|--------------------------------------|
| Classes         | PascalCase                  | `CompletionResponse`, `McpServer`    |
| Abstract classes| `Abstract` prefix           | `AbstractProvider`, `AbstractTool`   |
| Interfaces      | PascalCase + `Interface`    | `LlmInterface`, `ContentInterface`  |
| Traits          | PascalCase + `Trait`        | `FileContentTrait`                   |
| Enums           | PascalCase                  | `Capability`, `SelectionStrategy`    |
| Enum cases      | UPPER_SNAKE_CASE            | `JSON_OUTPUT`, `BEST_QUALITY`        |
| Constants       | UPPER_SNAKE_CASE            | `KNOWN_FIELDS`                       |
| Methods         | camelCase                   | `getProviders()`, `createUri()`      |
| Properties      | camelCase                   | `$apiKey`, `$maxTokens`              |
| Files           | PascalCase (matches class)  | `PayloadBuilder.php`                 |
| Namespaces      | PascalCase, domain-oriented | `Completion\Content`, `Mcp\Transport`|

Method prefixes: `get` for getters, `with` for immutable copy methods, `has`/`is` for booleans,
`create`/`from` for factory methods.

### Imports

All `use` statements are sorted alphabetically by fully-qualified class name in a single
continuous block with no blank lines between groups. This is enforced by EditorConfig
(`ij_php_import_sorting = alphabetic`).

### Type System

- Explicit return types on **every** method -- no exceptions.
- Union types are used extensively: `CompletionInterface|string`, `ModelInfo|string|null`.
- Nullable shorthand `?Type` for single-type nullable parameters; `Type|null` in unions.
- Use interfaces in method signatures for abstraction; concrete types only in constructors/factories.
- Typed array annotations via PHPDoc: `/** @var BuilderInterface[] */` or `/** @var array<string, ToolInterface> */`.
- No `mixed` except where genuinely required (e.g., `BuilderInterface::supports(mixed $value)`).

### Immutability

- `readonly` is the default for classes. Use it unless the class requires mutable state.
- Immutable value objects use the `with*()` pattern -- each returns a new instance:
  ```php
  $completion->withModel('gpt-4')->withMaxTokens(2000)->withTemperature(0.7);
  ```
- Only classes that manage internal state (e.g., `Usage`, `McpServer`) are non-readonly.

### Error Handling

- Throw `RuntimeException` or `InvalidArgumentException` from PHP SPL -- no custom exception classes.
- Use `sprintf()` for descriptive error messages with contextual data.
- Null-safe operator for optional dependencies: `$logger?->debug(...)`.
- Null-coalescing for defaults: `$json['usage']['prompt_tokens'] ?? 0`.
- Try/catch used strategically for failover (catch `Throwable`) and retry (catch `RuntimeException`).
- `try/finally` for resource cleanup.

### PHP 8.3+ Features

The codebase uses modern PHP extensively. Maintain consistency with:

- Constructor property promotion in every constructor
- `match` expressions instead of `switch`
- `readonly` classes and properties
- `enum` with string-backed values
- `#[Override]` attribute on every interface method implementation
- `#[SensitiveParameter]` on API keys and secrets
- Arrow functions (`fn()`) for short lambdas
- Spread operator: `[...$this->messages, $message]`
- Named arguments in constructors
- Variadic typed parameters: `ToolInterface ...$tools`
- First-class callable syntax: `$callback(...)`

### Documentation

- PHPDoc blocks on all interface methods with `@param`, `@return`, `@throws` tags.
- Implementations: PHPDoc only when adding information beyond the type signature.
- Inline `/** @var Type */` for typed arrays that PHP cannot express natively.
- `@internal` on classes not intended for external use (e.g., `AbstractProvider`).
- `@see` with URLs for external specification references.
- Short inline comments for non-obvious logic only.

### Test Conventions

- Tests live in `tests/`, mirroring the `src/` directory structure.
- Test classes extend `PHPUnit\Framework\TestCase` (or an abstract like `ProviderTestCase`).
- Test file naming: `{ClassName}Test.php`.
- Test method naming: `testMethodName()` or `testMethodNameWithCondition()` (camelCase).
- Use PHPUnit 12 attributes: `#[CoversClass(Foo::class)]`, `#[UsesClass(Bar::class)]`.
- Coverage metadata is **required** on all test classes (enforced by PHPUnit config).
- Use `$this->createMock()` and `$this->createStub()` for test doubles.

## Architecture

### Directory Layout

```
src/
  Llm.php, LlmInterface.php       # Main facade (failover across providers)
  Retry.php                        # Retry decorator
  Completion/                      # Core domain: completions, messages, content, tools
    Content/                       # Multimodal content types (text, image, audio, document, JSON)
    Message/                       # Message types and roles
    ResponseFormat/                # Structured output formats
    Tool/                          # Function calling / tool use system
  Mcp/                             # MCP (Model Context Protocol) client
    Transport/                     # Transport abstraction (HTTP Streamable)
  Model/                           # Model metadata, capabilities, selection strategies
  Payload/                         # HTTP payload serialization (builder chain)
    Builder/                       # Per-type payload builders
  Provider/                        # LLM provider implementations
  Usage/                           # Token usage tracking
```

### Key Design Patterns

- **Decorator**: `Retry` wraps `LlmInterface` for retry-with-backoff; `Llm` wraps multiple providers for failover.
- **Template Method**: `AbstractProvider.chat()` defines the algorithm; subclasses override `createUri()`.
- **Builder/Chain of Responsibility**: `PayloadBuilder` delegates to typed builders via `supports()`/`build()`.
- **Immutable Value Objects**: `Completion`, `Message`, content classes -- all `readonly` with `with*()` methods.
- **Factory**: `ContentFactory::create()` dispatches content creation via `match(true)`.
- **Lazy Initialization**: `AbstractServer::ensureInitialized()` performs MCP handshake on first access.
