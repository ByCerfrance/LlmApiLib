# Architecture

[Back to documentation](README.md)

## Goal

Find a class from its responsibility, and keep each integration independent. The library is organized as a modular monolith: each module is isolated in its namespace, with dependency rules enforced by tests.

## Namespaces

```text
src/
├── Chat/                      Execution, requests, responses and their decorators
│   ├── Conversation/          Immutable history
│   │   └── Message/           Messages and roles
│   ├── Guard/                 Response guards and GuardException
│   ├── ResponseFormat/        Structured output formats
│   └── Streaming/             Progressive execution, events and cancellation
├── Content/                   Multimodal message contents
├── Tool/                      Tool contracts, collections, execution and validation
├── Image/                     Standalone image generation and its decorators
├── Model/                     Technical model metadata and capabilities
├── Usage/                     Measurements, records, collectors and snapshots
│   └── Pricing/               Rates and cost estimation
├── Observation/               Execution events
├── Resilience/                Shared retry policy and loop
├── Payload/                   Generic payload serialization and model constraints
├── Transport/                 Progressive HTTP (cURL) and SSE decoding
├── Provider/                  Provider contracts, exceptions and decorators
│   ├── OpenAiCompatible/      Shared OpenAI-compatible protocol and Generic endpoint
│   ├── OpenAi/                OpenAI chat, streaming and images
│   ├── Google/                Google compatibility endpoint and native Gemini
│   ├── Mistral/
│   └── Ovh/
├── Mcp/                       MCP client and its transport
└── OpenApi/                   REST tools discovered from an OpenAPI specification
```

Sub-namespaces exist only where they represent a responsibility: there are no systematic `Interface`, `Exception` or `Decorator` folders. An exception lives with the feature that throws it (`Chat\Guard\GuardException`, `Provider\ProviderException`), and a decorator lives with the contract it decorates (`Chat\RetryChat`, `Provider\RetryProvider`).

## Modules

| Module | Namespaces | May depend on |
| --- | --- | --- |
| Core | everything not listed below | — |
| OpenAI-compatible | `Provider\OpenAiCompatible` | Core |
| OpenAI | `Provider\OpenAi` | Core, OpenAI-compatible |
| Google | `Provider\Google` | Core, OpenAI-compatible |
| Mistral | `Provider\Mistral` | Core, OpenAI-compatible |
| OVH | `Provider\Ovh` | Core, OpenAI-compatible |
| MCP | `Mcp` | Core |
| OpenAPI | `OpenApi` | Core |

```text
OpenAi ───────┐
Mistral ──────┤
Ovh ──────────┼──► OpenAiCompatible ──► Core
Google ───────┘                          ▲
Google native ───────────────────────────┤
Mcp ─────────────────────────────────────┤
OpenApi ─────────────────────────────────┘
```

**The core never depends on an integration.** It exposes the contracts (`ChatProviderInterface`, `ImageProviderInterface`, `ToolCollectionInterface`…) and the shared mechanisms; integrations implement them. Integrations never depend on each other, except through the shared OpenAI-compatible protocol.

`tests/ArchitectureTest.php` enforces these rules on every source and test file. It also checks that the optional OpenAPI parser (`cebe\openapi`) is only used by the OpenAPI module.

## Where does a class go?

- **Specific to one provider** — its request mapping, payload builder, response parsing or configuration — goes in that provider's namespace. Example: `Provider\OpenAi\OpenAiChatBuilder` sends documents as inline PDF files, `Provider\Mistral\MistralChatBuilder` maps `max_tokens`.
- **Shared by OpenAI-compatible endpoints** goes in `Provider\OpenAiCompatible`. It must not know its consumers: a provider customizes the protocol by overriding `getPayloadBuilders()`, not by being tested with `instanceof`.
- **Needed by every provider implementation** — contracts, `ProviderException`, `JsonTransport`, retry and failover decorators — stays directly in `Provider`.
- **Provider-independent mechanisms** stay in their core namespace: `Payload\PayloadBuilder` and `Payload\ModelChatBuilder` are used by both the compatible and the native Google protocols.
- **Remote tool sources** extend `Tool\AbstractLazyToolCollection`, which discovers tools on first access.

## Tests

Tests mirror the source tree and follow the same module rules as the code they test. Core tests never go through a provider protocol: they use the provider doubles in `tests/Fixture` (`ScriptedChatProvider`, `ScriptedStreamingProvider`), which replay scripted turns and record the requests they receive. Provider-specific tests live with their module, for example `tests/Provider/Mistral`. OpenAI-compatible providers extend `tests/Provider/OpenAiCompatible/OpenAiCompatibleTestCase.php`, which runs the shared conformance checks.

Related: [Providers](providers.md), [Composition](composition.md), [Migrating from 1.x](../MIGRATION-2.x.md).
