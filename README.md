# LLM API Library — 2.x

Composable PHP 8.3+ clients for chat, tools, images, streaming and MCP. Providers handle protocols; executors bind models and run conversations. Applications own model selection and orchestration roles.

See [migration from 1.x](MIGRATION-2.x.md), [changelog](CHANGELOG.md), and [executable examples](examples/README.md).

## Documentation

Start with [Getting started](docs/getting-started.md), then explore [Conversations](docs/conversations.md), [Tools](docs/tools.md) and [Streaming](docs/streaming.md).

The [documentation index](docs/README.md) links all guides, including providers, images, usage/pricing, MCP, OpenAPI and application composition. Guides use short explained examples; repository scripts run offline by default.

## Installation

```shell
composer require bycerfrance/llm-api-lib:dev-2.x
```

Supply a PSR-18 HTTP client. Progressive HTTP streaming additionally requires ext-curl. OpenAPI tool discovery optionally requires `devizzent/cebe-php-openapi`.

## Chat

```php
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAi;

$chat = new Chat(new OpenAi($apiKey, $httpClient), new Model($modelName));
$response = $chat->chat('Explain crop rotation briefly.');
echo $response->getText();
```

Use ChatRequest and Conversation for system instructions, continuation and independent branches. Optional settings remain null unless supplied; explicit zero is retained. Known model incompatibilities fail by default. Missing usage is unknown, not zero.

## Composition

- **ChatInterface**: complete conversations; Chat owns sequential tool execution and history.
- **ChatProviderInterface**: one provider turn. RetryProvider retries a turn; FailoverProvider tries application-ordered routes.
- **ImageGeneratorInterface**: standalone images, independent of chat. Typed image retry, failover and usage decorators are available.
- **StreamingChatInterface**: progressive events and an assembled final response. Close streams explicitly; retries stop once output has been emitted.
- **UsageCollector**: detailed composites or compact snapshots. Pricing is separate and supports cache rates, unknown values and distinct currencies.
- **MCP/OpenAPI**: reuse tool collections through ChatRequest. MCP supports opt-in progressive JSON/SSE responses.

Register executors under application-defined roles in your orchestrator. The library contains no labels, subjective quality tiers, scores or automatic model ranking.

## Architecture

Namespaces follow responsibilities: `Chat` (with `Conversation`, `Guard`, `Streaming`), `Content`, `Tool`, `Image`, `Model`, `Usage`, `Provider`, `Mcp` and `OpenApi`. Each provider lives in its own namespace (`Provider\OpenAi`, `Provider\Google`…) and only depends on the core and the shared `Provider\OpenAiCompatible` protocol. These dependency rules are enforced by tests. See [Architecture](docs/architecture.md).

## Provider support

| Provider | Synchronous chat/tools | Streaming | Image generation |
| --- | --- | --- | --- |
| OpenAi | Yes | StreamingOpenAi, text/tools | OpenAiImages, standalone generation |
| Mistral, OVH, Generic | OpenAI-compatible transport | Not advertised | Not advertised |
| Google | OpenAI compatibility endpoint | Not advertised | Not assumed |
| GoogleNative | v1beta GenerateContent | Not advertised | Conversational generation/editing with native signatures |

Options and capabilities depend on the selected model. Multiple chat choices, generated audio, standalone image editing, image streaming and MCP stream resumption are not implemented.

## Examples and checks

```shell
composer install
composer run test
composer run analyse
php examples/chat.php
php examples/tools.php
php examples/resilience.php
php examples/usage.php
php examples/images.php
php examples/google-images.php
php examples/streaming.php
php examples/mcp-sse.php
```

Examples are offline by default. Image and streaming examples accept explicit `--live` execution with credentials. Deterministic tests cover wire formats, tools, forks, usage and cleanup; a local HTTP test verifies progressive delivery.

See [examples/README.md](examples/README.md) for configuration, accounting boundaries, recovery semantics and protocol limits. Historical release notes remain in [CHANGELOG-1.x.md](CHANGELOG-1.x.md).
