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
vendor/bin/phpunit tests/Chat/ChatTest.php

# Run a single test method
vendor/bin/phpunit --filter testMethodName

# Run a single test method within a specific class
vendor/bin/phpunit --filter 'ChatTest::testMethodName'

# Run tests with coverage output
vendor/bin/phpunit --coverage-text

# Run static analysis (PHPStan)
composer run analyse
```

PHPUnit is configured via `phpunit.xml.dist`. The test suite is strict: it enforces coverage
metadata (`requireCoverageMetadata`), fails on risky tests, warnings, and PHPUnit deprecations.

PHPStan runs against both `src/` and `tests/` with default configuration (no `phpstan.neon`).

## Commit Guidelines

- Use Conventional Commits: `<type>(<optional scope>): <description>`.
- Write concise commit messages in English, using an imperative description.
- Common types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `build`, and `ci`.
- Mark breaking API changes with `!` and explain the migration in a `BREAKING CHANGE:` footer.
- Examples: `feat(chat): add immutable conversations`, `docs: document v2 migration`.
- Commit only reviewed changes authorized by the user; never include credentials or local `.notes/` files.

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
| Classes         | PascalCase                  | `ChatResponse`, `McpServer`          |
| Abstract classes| `Abstract` prefix           | `AbstractOpenAiCompatible`, `AbstractTool` |
| Interfaces      | PascalCase + `Interface`    | `ChatInterface`, `ContentInterface`  |
| Traits          | PascalCase + `Trait`        | `FileContentTrait`                   |
| Enums           | PascalCase                  | `Capability`, `FailureKind`          |
| Enum cases      | UPPER_SNAKE_CASE            | `JSON_OUTPUT`, `RATE_LIMIT`          |
| Constants       | UPPER_SNAKE_CASE            | `KNOWN_FIELDS`                       |
| Methods         | camelCase                   | `getProviders()`, `createUri()`      |
| Properties      | camelCase                   | `$apiKey`, `$maxTokens`              |
| Files           | PascalCase (matches class)  | `PayloadBuilder.php`                 |
| Namespaces      | PascalCase, domain-oriented | `Chat\Conversation`, `Provider\OpenAi`|

Method prefixes: `get` for getters, `with` for immutable copy methods, `has`/`is` for booleans,
`create`/`from` for factory methods.

### Imports

All `use` statements are sorted alphabetically by fully-qualified class name in a single
continuous block with no blank lines between groups. This is enforced by EditorConfig
(`ij_php_import_sorting = alphabetic`).

### Type System

- Explicit return types on **every** method -- no exceptions.
- Union types are used extensively: `ChatRequestInterface|string`, `int|float|null`.
- Nullable shorthand `?Type` for single-type nullable parameters; `Type|null` in unions.
- Use interfaces in method signatures for abstraction; concrete types only in constructors/factories.
- Typed array annotations via PHPDoc: `/** @var BuilderInterface[] */` or `/** @var array<string, ToolInterface> */`.
- No `mixed` except where genuinely required (e.g., `BuilderInterface::supports(mixed $value)`).

### Immutability

- `readonly` is the default for classes. Use it unless the class requires mutable state.
- Immutable value objects use the `with*()` pattern -- each returns a new instance:
  ```php
  $request->withMaxTokens(2000)->withTemperature(0.7);
  ```
- Only classes that manage internal state (e.g., `UsageCollector`, `McpServer`) are non-readonly.

### Error Handling

- Throw `RuntimeException` or `InvalidArgumentException` from PHP SPL for local errors.
- Custom exceptions only when they carry data the caller needs (`ProviderException`, `GuardException`); they
  extend `RuntimeException` and live with the feature that throws them.
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
- `@internal` on classes not intended for external use (e.g., provider payload builders).
- `@see` with URLs for external specification references.
- Short inline comments for non-obvious logic only.

### Test Conventions

- Tests live in `tests/`, mirroring the `src/` directory structure.
- Test classes extend `PHPUnit\Framework\TestCase` (or an abstract like `OpenAiCompatibleTestCase`, which runs
  the conformance checks shared by OpenAI-compatible providers).
- Provider-specific tests live with their module (e.g. `tests/Provider/Mistral`). Core tests never use a provider
  implementation: use the doubles in `tests/Fixture` (`ScriptedChatProvider`, `ScriptedStreamingProvider`).
- Test file naming: `{ClassName}Test.php`.
- Test method naming: `testMethodName()` or `testMethodNameWithCondition()` (camelCase).
- Use PHPUnit 12 attributes: `#[CoversClass(Foo::class)]`, `#[UsesClass(Bar::class)]`.
- Use `$this->createMock()` and `$this->createStub()` for test doubles.

### Coverage Metadata (strict enforcement)

PHPUnit is configured with `requireCoverageMetadata="true"`, `beStrictAboutCoverageMetadata="true"`,
and `failOnRisky="true"`. This means **every** test class must declare via attributes **all** classes
whose code it executes, directly or indirectly. If a test runs code from an undeclared class, PHPUnit
will fail with: _"This test executed code that is not listed as code to be covered or used"_.

Rules:

- `#[CoversClass(Foo::class)]`: the class under test (usually one per test class).
- `#[UsesClass(Bar::class)]`: every dependency that the test **indirectly executes**. This includes:
  - Classes instantiated directly in the test (`new Message('hello')`)
  - Classes instantiated transitively by the code under test (e.g., `Message` constructor creates
    `TextContent` from a string, OpenAI-compatible providers create a `JsonTransport`)
  - Parent classes of instantiated classes (e.g., `AbstractTool` when instantiating `Tool`)
  - Enums used during execution (e.g., `RoleEnum`, `Capability`)
  - Factory classes called by the code under test (e.g., `ContentFactory` called by `MessageFactory`)

When adding a new test or modifying existing code, trace the execution path and ensure all touched
classes from `src/` are declared. Without a coverage driver, a missing `#[UsesClass]` passes locally but
**fails on CI**: run `vendor/bin/phpunit --coverage-text` with Xdebug or PCOV enabled to check it.

## Architecture

The library is a modular monolith: each module is isolated in its namespace, with tested dependency
rules. See `docs/architecture.md` for the full rules.

### Directory Layout

```
src/
  Chat/                            # Chat execution, requests, responses, decorators (RetryChat, ChatUsage)
    Conversation/                  # Immutable history
      Message/                     # Message types and roles
    Guard/                         # Response guards and GuardException
    ResponseFormat/                # Structured output formats
    Streaming/                     # Progressive execution, events and cancellation
  Content/                         # Multimodal content types (text, image, audio, document, JSON)
  Tool/                            # Tool contracts, collections, execution, validation, lazy collections
  Image/                           # Standalone image generation and decorators
  Model/                           # Technical model metadata and capabilities
  Usage/                           # Usage records, collectors, snapshots
    Pricing/                       # Rates and cost estimation
  Observation/                     # Execution events
  Resilience/                      # Retry policy and loop
  Payload/                         # Generic payload serialization and model constraints
  Transport/                       # Progressive cURL HTTP and SSE decoding
  Provider/                        # Provider contracts, ProviderException, JsonTransport, decorators
    OpenAiCompatible/              # Shared OpenAI-compatible protocol and Generic endpoint
    OpenAi/  Google/  Mistral/  Ovh/   # One namespace per provider, with its builders
  Mcp/                             # MCP client
    Transport/                     # MCP transport (HTTP Streamable)
  OpenApi/                         # REST tools discovered from an OpenAPI specification
```

### Module Rules

- The core (everything outside `Provider\<Provider>`, `Mcp`, `OpenApi`) never depends on an integration.
- Providers depend only on the core and `Provider\OpenAiCompatible`; never on each other.
- Anything specific to one provider (payload builder, parsing, configuration) goes in its namespace. Shared
  protocol code must not test its consumers with `instanceof`: providers override `getPayloadBuilders()`.
- Create a sub-namespace only for a real responsibility; no `Interface`/`Exception`/`Decorator` folders.
- `tests/ArchitectureTest.php` enforces these dependencies, for source and test files alike.

### Key Design Patterns

- **Decorator**: `RetryProvider`/`FailoverProvider` wrap one provider turn; `RetryChat`, `GuardChat`, `ChatUsage` wrap a chat.
- **Template Method**: `AbstractOpenAiCompatible` builds and parses requests; subclasses override `createUri()` and `getPayloadBuilders()`.
- **Builder**: `PayloadBuilder` recursively resolves `JsonSerializable` objects; provider-specific builders override via `supports()`/`build()`.
- **Immutable Value Objects**: `ChatRequest`, `Conversation`, messages, content classes -- all `readonly` with `with*()` methods.
- **Factory**: `ContentFactory::create()` dispatches content creation via `match(true)`.
- **Lazy Initialization**: `AbstractLazyToolCollection::ensureInitialized()` discovers remote tools (MCP handshake, OpenAPI parsing) on first access.
