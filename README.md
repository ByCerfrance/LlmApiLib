# LLM API Library

[![Latest Version](https://img.shields.io/packagist/v/bycerfrance/llm-api-lib.svg?style=flat-square)](https://github.com/ByCerfrance/LlmApiLib/releases)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/bycerfrance/llm-api-lib/php?version=dev-main&style=flat-square)
[![Software license](https://img.shields.io/github/license/ByCerfrance/LlmApiLib.svg?style=flat-square)](https://github.com/ByCerfrance/LlmApiLib/blob/main/LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/ByCerfrance/LlmApiLib/tests.yml?branch=main&style=flat-square&label=tests)](https://github.com/ByCerfrance/LlmApiLib/actions/workflows/tests.yml?query=branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/bycerfrance/llm-api-lib.svg?style=flat-square)](https://packagist.org/packages/bycerfrance/llm-api-lib)

PHP 8.3+ library for interacting with multiple LLM providers (Google, Mistral, OpenAI, OVH and any OpenAI-compatible
endpoint) with failover, retry, guard validation, tool calling, MCP client, and OpenAPI integration support.

## Installation

You can install library with [Composer](https://getcomposer.org/), it's the recommended installation.

```bash
$ composer require bycerfrance/llm-api-lib
```

## Providers

### Built-in providers

- **Google** -- Google Generative Language API
- **Mistral** -- Mistral AI API
- **OpenAI** -- OpenAI API
- **OVH** -- OVH AI Endpoints

### Generic (OpenAI-compatible)

The `Generic` provider connects to any OpenAI-compatible endpoint (local servers, proxies, third-party providers):

```php
use ByCerfrance\LlmApiLib\Provider\Generic;

$provider = new Generic(
    uri: 'https://my-local-server.com/v1/chat/completions',
    apiKey: 'my-api-key',
    model: 'my-model',
    client: $httpClient, // PSR-18 ClientInterface
);
```

### Model metadata

Use `ModelInfo` to attach rich metadata to a provider (capabilities, quality/cost tiers, pricing, context window):

```php
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Model\QualityTier;
use ByCerfrance\LlmApiLib\Model\CostTier;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Provider\OpenAi;

$model = new ModelInfo(
    name: 'gpt-4o',
    capabilities: [Capability::TEXT, Capability::IMAGE, Capability::TOOLS, Capability::JSON_OUTPUT],
    qualityTier: QualityTier::PREMIUM,
    costTier: CostTier::HIGH,
    inputCost: 2.50,   // $ per million tokens
    outputCost: 10.00,  // $ per million tokens
    maxContextTokens: 128_000,
);

$provider = new OpenAi(
    apiKey: 'sk-...',
    model: $model,       // ModelInfo or plain string
    client: $httpClient,
);
```

## Chat

### Basic usage

```php
$llm = new \ByCerfrance\LlmApiLib\Llm($provider);

$completion = $llm->chat('Salut !');
print $completion->getLastMessage()->getContent(); // "Salut ! Comment allez-vous ?"

$completion = $llm->chat($completion->withNewMessage('Bien merci et toi ?'));
print $completion->getLastMessage()->getContent(); // "Bien, merci. Comment puis-je vous aider ?"
```

### With instructions

```php
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\Message\SystemMessage;
use ByCerfrance\LlmApiLib\Llm;

$completion = new Completion(new SystemMessage(
    'Tu es un assistant comptable, presentes toi comme tel.',
));

$llm = new Llm($provider);

$completion = $llm->chat($completion->withNewMessage('Salut !'));
print $completion->getLastMessage()->getContent();
// "Bonjour, je suis votre assistant comptable. Comment puis-je vous aider ?"
```

### Message types

The library provides typed message classes for convenience:

```php
use ByCerfrance\LlmApiLib\Completion\Message\SystemMessage;
use ByCerfrance\LlmApiLib\Completion\Message\UserMessage;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;

// Typed classes (recommended)
$system = new SystemMessage('You are a helpful assistant.');
$user = new UserMessage('Hello!');

// Or using the generic Message class with explicit role
$system = new Message('You are a helpful assistant.', role: RoleEnum::SYSTEM);
```

### Completion parameters

Fine-tune the LLM behavior with immutable `with*()` methods:

```php
use ByCerfrance\LlmApiLib\Completion\Completion;

$completion = (new Completion(['Explain quantum computing']))
    ->withModel('gpt-4o')           // Override the provider's default model
    ->withMaxTokens(2000)           // Maximum tokens in the response
    ->withTemperature(0.7)          // Creativity (0 = deterministic, 2 = very creative)
    ->withTopP(0.9)                 // Nucleus sampling
    ->withSeed(42);                 // Reproducible outputs (provider-dependent)
```

### Service tier

Control the processing priority and pricing tier with `ServiceTier`:

```php
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\ServiceTier;

$completion = (new Completion(['Summarize this report']))
    ->withServiceTier(ServiceTier::FLEX);
```

| Value      | Description                                    |
|------------|------------------------------------------------|
| `AUTO`     | Let the provider choose the best tier          |
| `DEFAULT`  | Standard processing                            |
| `FLEX`     | Lower-priority, reduced-cost processing        |
| `PRIORITY` | Higher-priority processing                     |

Each value defines a `fallback()` method for provider compatibility: `PRIORITY` → `AUTO` → `DEFAULT`, `FLEX` → `AUTO` →
`DEFAULT`. Providers that do not support `service_tier` (e.g., Mistral) automatically strip it from the payload.

### Reasoning effort

Control how much reasoning the model performs before generating a response with `ReasoningEffort`:

```php
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\ReasoningEffort;

$completion = (new Completion(['Solve this math problem step by step']))
    ->withReasoningEffort(ReasoningEffort::HIGH);
```

| Value    | Description                                     |
|----------|-------------------------------------------------|
| `NONE`   | No reasoning traces                             |
| `LOW`    | Minimal reasoning                               |
| `MEDIUM` | Moderate reasoning                              |
| `HIGH`   | Full reasoning traces                           |
| `XHIGH`  | Extended reasoning (OpenAI o3/o4-mini)          |

Each value defines a `fallback()` method: `XHIGH` → `HIGH` → `MEDIUM` → `LOW` → `NONE`. Provider-specific builders
use this chain to map unsupported values to the closest supported one. For example, Mistral only supports `HIGH` and
`NONE`, so `MEDIUM` falls back through `LOW` → `NONE`.

Setting `reasoningEffort` automatically adds `Capability::REASONING` to the completion's required capabilities, ensuring
only providers that support reasoning are selected during failover.

## Content Types

### ArrayContent

Allows combining multiple contents (`ContentInterface` or strings) into a single object. Useful for sending multiple
elements in a single message.

Example:

```php
$content = new ArrayContent(
    new TextContent('First message'),
    'Second message'
);
```

### DocumentUrlContent

Represents a document accessible via a URL. Supports capabilities `document` and `ocr`.

Example:

```php
$content = new DocumentUrlContent('https://example.com/document.pdf');
```

Creates a `DocumentUrlContent` instance from a local file path or stream. The file is automatically converted to a
base64-encoded data URL.

Example:

```php
$content = DocumentUrlContent::fromFile('/path/to/document.pdf', 'custom-name.pdf');
```

Parameters:

- `$file`: Path to the file as a string or a stream resource.
- `$name`: Optional custom name for the document.
- `$detail`: Optional detail level for processing (e.g., 'auto', 'low', 'high').

### ImageUrlContent

Represents an image accessible via a URL. Supports capabilities `image` and `ocr`.

Example:

```php
$content = new ImageUrlContent('https://example.com/image.jpg');
```

Creates an `ImageUrlContent` instance from a GD image resource. The image is automatically converted to a base64-encoded
data URL.

Example:

```php
$content = ImageUrlContent::fromGdImage($gdImage, 'high');
```

Parameters:

- `$image`: A GD image resource.
- `$detail`: Optional detail level for processing (e.g., 'auto', 'low', 'high').
- `$maxSize`: Optional maximum size for resizing the image.
- `$format`: Optional image format ('jpeg', 'png', 'gif', 'webp').
- `$quality`: Optional quality setting for JPEG/PNG/WebP formats.

Creates an `ImageUrlContent` instance from a local file path or stream. The file is automatically converted to a
base64-encoded data URL.

Example:

```php
$content = ImageUrlContent::fromFile('/path/to/image.png', 'low');
```

Parameters:

- `$file`: Path to the file as a string or a stream resource.
- `$detail`: Optional detail level for processing (e.g., 'auto', 'low', 'high').

### InputAudioContent

Represents audio content encoded in base64 with a specified format. Supports capability `audio`.

Example:

```php
$content = new InputAudioContent('base64encodeddata', 'wav');
```

### TextContent & JsonContent

`TextContent` represents plain text or text read from a file. It supports the `text` capability.

`JsonContent` represents structured data in JSON format. It also supports the `text` capability.

Examples:

```php
$text = new TextContent('Hello, world!');
$json = new JsonContent(['key' => 'value']);
```

When creating a `TextContent` instance, you can pass an associative array of placeholders that will be applied to the
content using `str_replace`. This allows dynamic content generation based on placeholders in the text.

Example:

```php
$content = new TextContent('Hello {name}, you are {age} years old.', ['name' => 'John', 'age' => 30]);
echo $content; // Outputs: "Hello John, you are 30 years old."
```

The placeholders are applied using the format `{key}` where `key` corresponds to the keys in the placeholder array.

Creates a `TextContent` instance from a local file path or stream. The file content is automatically loaded and can be
processed with optional placeholders.

Example:

```php
$content = TextContent::fromFile('/path/to/text.txt', ['name' => 'John', 'age' => 30]);
```

Parameters:

- `$file`: Path to the file as a string or a stream resource.
- `$placeholders`: Optional associative array of placeholders to apply to the content.

## Response Formats

Control the output format of the LLM response using `withResponseFormat()`.

### Text (default)

```php
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\TextFormat;

$completion = (new Completion(['Explain gravity']))
    ->withResponseFormat(new TextFormat());
```

### JSON Object

Forces the LLM to return valid JSON. Requires a provider with `JSON_OUTPUT` capability.

```php
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonObjectFormat;

$completion = (new Completion(['List 3 colors as a JSON array']))
    ->withResponseFormat(new JsonObjectFormat());

$response = $llm->chat($completion);
$data = json_decode($response->getLastMessage()->getContent(), true);
```

### JSON Schema

Forces the LLM to return JSON conforming to a specific schema. Requires a provider with `JSON_SCHEMA` capability.

```php
use ByCerfrance\LlmApiLib\Completion\ResponseFormat\JsonSchemaFormat;

$completion = (new Completion(['Describe a person']))
    ->withResponseFormat(new JsonSchemaFormat(
        name: 'person',
        schema: [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name', 'age'],
        ],
        strict: true,
    ));

$response = $llm->chat($completion);
// {"name": "John", "age": 30}
```

## Tools (Function Calling)

Tools allow the LLM to call external functions during inference. The library handles the tool execution loop
automatically.

### Defining a tool

```php
use ByCerfrance\LlmApiLib\Completion\Tool\Tool;

$weatherTool = new Tool(
    name: 'get_weather',
    description: 'Get the current weather for a location',
    parameters: [
        'type' => 'object',
        'properties' => [
            'location' => [
                'type' => 'string',
                'description' => 'The city name',
            ],
        ],
        'required' => ['location'],
    ],
    callback: function (array $arguments): array {
        // Your logic here
        return [
            'temperature' => 20,
            'unit' => 'celsius',
            'condition' => 'sunny',
        ];
    },
);
```

### Using tools in a completion

```php
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Llm;

$completion = (new Completion(['Quel temps fait-il a Paris ?']))
    ->withTools($weatherTool)
    ->withMaxToolIterations(5); // Optional, default is 10

$llm = new Llm($provider);
$response = $llm->chat($completion);

print $response->getLastMessage()->getContent();
// "Il fait actuellement 20°C a Paris avec un temps ensoleille."
```

### Multiple tools

```php
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollection;

$completion = (new Completion(['...']))
    ->withTools($weatherTool, $calculatorTool, $searchTool);

// Or using a collection
$tools = new ToolCollection($weatherTool, $calculatorTool);
$completion = (new Completion(['...']))->withTools($tools);
```

### Filtered tools

Use `FilteredToolCollection` to restrict which tools are visible to the LLM. Supports include patterns (whitelist) and
exclude patterns (prefix with `!`):

```php
use ByCerfrance\LlmApiLib\Completion\Tool\FilteredToolCollection;

// Only expose specific tools
$filtered = new FilteredToolCollection($toolCollection, ['get_weather', 'search']);

// Exclude specific tools (expose everything else)
$filtered = new FilteredToolCollection($toolCollection, ['!delete_user', '!drop_table']);
```

### Tool choice

Control whether and how the model should use tools with `ToolChoice`:

```php
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\ToolChoice;

$completion = (new Completion(['What is the weather in Paris?']))
    ->withTools($weatherTool)
    ->withToolChoice(ToolChoice::REQUIRED);
```

| Value      | Description                                      |
|------------|--------------------------------------------------|
| `AUTO`     | The model decides whether to call tools (default)|
| `NONE`     | The model must not call any tool                 |
| `REQUIRED` | The model must call at least one tool            |

When `null` (default), `tool_choice` is not sent in the payload and the provider uses its own default (typically `auto`).
Mistral uses `any` instead of `required`; the library remaps this automatically.

### Parallel tool calls

Control whether the model can issue multiple tool calls in a single turn:

```php
$completion = (new Completion(['...']))
    ->withTools($weatherTool, $calculatorTool)
    ->withParallelToolCalls(false); // Force sequential tool calls
```

When `null` (default), `parallel_tool_calls` is not sent in the payload and the provider uses its own default (typically
`true`). Set to `false` to force the model to call tools one at a time.

### Tool execution loop

The library automatically:

- Sends tools definition to the LLM
- Detects when the LLM wants to call a tool
- Executes the callback with the provided arguments
- Sends the result back to the LLM
- Continues until the LLM provides a final response or max iterations is reached

## MCP Client (Model Context Protocol)

The library includes a full MCP client that connects to remote MCP servers, discovers tools, and executes them. MCP
servers implement `ToolCollectionInterface` and can be passed directly to `withTools()`.

### McpServer

```php
use ByCerfrance\LlmApiLib\Mcp\McpServer;
use ByCerfrance\LlmApiLib\Mcp\Transport\HttpStreamable;

// Create transport
$transport = new HttpStreamable(
    uri: 'https://my-mcp-server.com/mcp',
    client: $httpClient,
    headers: ['Authorization' => 'Bearer my-token'],
);

// Create MCP server client
$mcp = new McpServer($transport);

// Use MCP tools in a completion (tools are discovered automatically via lazy initialization)
$completion = (new Completion(['Search for documents about PHP']))
    ->withTools($mcp);

$response = $llm->chat($completion);
```

The MCP client handles the full lifecycle: initialization handshake, tool discovery (with pagination), tool execution
via
JSON-RPC `tools/call`, and graceful shutdown.

### OpenAPI integration

Connect to any REST API described by an OpenAPI 3.x specification. Each operation becomes a tool the LLM can call.

> Requires the optional dependency: `composer require devizzent/cebe-php-openapi`

```php
use ByCerfrance\LlmApiLib\Mcp\OpenApi;
use cebe\openapi\Reader;

$spec = Reader::readFromJsonFile('/path/to/openapi.json');

$openApi = new OpenApi(
    spec: $spec,
    client: $httpClient,
    headers: ['Authorization' => 'Bearer api-token'],
    baseUrl: 'https://api.example.com', // Optional, overrides spec servers
);

// Use OpenAPI operations as tools
$completion = (new Completion(['List all users']))
    ->withTools($openApi);

// Or filter specific operations
$filtered = new FilteredToolCollection($openApi, ['listUsers', 'getUser']);
$completion = (new Completion(['List all users']))
    ->withTools($filtered);

$response = $llm->chat($completion);
```

## LlmTool (Agentic Sub-Model Delegation)

`LlmTool` allows the orchestrator LLM to delegate tasks to a different model via tool calling:

```php
use ByCerfrance\LlmApiLib\Completion\Tool\LlmTool;
use ByCerfrance\LlmApiLib\Completion\Completion;

$analysisTool = new LlmTool(
    name: 'analyze_code',
    description: 'Analyze code for security vulnerabilities',
    parameters: [
        'type' => 'object',
        'properties' => [
            'code' => ['type' => 'string', 'description' => 'The code to analyze'],
            'language' => ['type' => 'string', 'description' => 'Programming language'],
        ],
        'required' => ['code'],
    ],
    llm: $specializedProvider, // A different LlmInterface (e.g., a more powerful model)
    promptBuilder: fn(string $code, string $language = 'php') => new Completion([
        "Analyze this {$language} code for security issues:\n{$code}",
    ]),
);

$completion = (new Completion(['Review my application for security issues']))
    ->withTools($analysisTool);

$response = $llm->chat($completion);

// Aggregate usage/cost across all sub-model calls
$subModelUsage = $analysisTool->getLlm()->getUsage();
$subModelCost = $analysisTool->getLlm()->getCost();
```

## Model Selection

### Selection strategy

When using multiple providers with `Llm`, control which provider is preferred:

```php
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Completion\Completion;

$completion = (new Completion(['Complex reasoning task']))
    ->withSelectionStrategy(SelectionStrategy::BEST_QUALITY);
```

Available strategies:

| Strategy       | Description               | Scoring formula        |
|----------------|---------------------------|------------------------|
| `CHEAP`        | Prefer low-cost providers | 80% cost + 20% quality |
| `BALANCED`     | Balance cost and quality  | 50% cost + 50% quality |
| `BEST_QUALITY` | Prefer highest quality    | 80% quality + 20% cost |

Scoring is based on the `QualityTier` (BASIC, GOOD, PREMIUM) and `CostTier` (LOW, MEDIUM, HIGH) defined in each
provider's `ModelInfo`.

## Response Handling

### CompletionResponseInterface

The `chat()` method returns a `CompletionResponseInterface` which extends `CompletionInterface` with additional response
data:

```php
$response = $llm->chat('Hello');

// Access the response content
$content = $response->getLastMessage()->getContent();

// Per-request token usage
$usage = $response->getUsage();
echo $usage->getPromptTokens();
echo $usage->getCompletionTokens();
echo $usage->getTotalTokens();

// Finish reason
$finishReason = $response->getFinishReason(); // FinishReason::STOP, LENGTH, TOOL_CALLS, CONTENT_FILTER

// Continue the conversation (CompletionResponseInterface extends CompletionInterface)
$response = $llm->chat($response->withNewMessage('Follow up question'));
```

### FinishReason

The `FinishReason` enum indicates why the LLM stopped generating:

| Value            | Description                                          |
|------------------|------------------------------------------------------|
| `STOP`           | Normal completion                                    |
| `LENGTH`         | Maximum token limit reached                          |
| `TOOL_CALLS`     | Model wants to call tools (handled automatically)    |
| `CONTENT_FILTER` | Content was filtered by the provider's safety system |

## Retry

The `Retry` decorator wraps any `LlmInterface` and retries on failure with configurable backoff:

```php
use ByCerfrance\LlmApiLib\Retry;

$retryableProvider = new Retry(
    provider: $provider,
    time: 5000,          // Base wait time in milliseconds (default: 5000)
    retry: 3,            // Maximum retry attempts (default: 2)
    multiplier: 2.0,     // Exponential backoff multiplier (default: 1 = constant delay)
    retryOnGuard: false, // Retry on GuardException (default: false)
);

// Wait times: 5s, 10s, 20s (time * multiplier^attempt)
$response = $retryableProvider->chat('Hello');
```

## Guard System

Guards validate LLM responses after each `chat()` call. If validation fails, a `GuardException` is thrown with the
rejected response attached.

### Custom guard

```php
use ByCerfrance\LlmApiLib\Guard\Guard;
use ByCerfrance\LlmApiLib\Guard\GuardException;

$guarded = new Guard(
    provider: $provider,
    guard: function (\ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface $response): void {
        $content = $response->getLastMessage()->getContent();
        if (str_contains($content, 'I cannot')) {
            throw new \RuntimeException('Response contains a refusal');
        }
    },
);

try {
    $response = $guarded->chat('...');
} catch (GuardException $e) {
    $rejectedResponse = $e->getResponse(); // Access the rejected response
    echo $e->getMessage();
}
```

### FinishReasonGuard

A built-in guard that rejects responses with specific finish reasons (defaults to `LENGTH` and `CONTENT_FILTER`):

```php
use ByCerfrance\LlmApiLib\Guard\FinishReasonGuard;
use ByCerfrance\LlmApiLib\Completion\FinishReason;

// Default: rejects LENGTH and CONTENT_FILTER
$guarded = new FinishReasonGuard($provider);

// Custom: only reject LENGTH
$guarded = new FinishReasonGuard($provider, FinishReason::LENGTH);
```

### Combining Guard + Retry

Guards and retries compose naturally as decorators:

```php
use ByCerfrance\LlmApiLib\Guard\FinishReasonGuard;
use ByCerfrance\LlmApiLib\Retry;

// Retry up to 3 times if the response is truncated (LENGTH) or filtered
$robust = new Retry(
    provider: new FinishReasonGuard($provider),
    retry: 3,
    retryOnGuard: true, // Required to retry on GuardException
);

$response = $robust->chat('...');
```

## Failover

The `Llm` class accepts multiple providers and implements automatic failover:

```php
use ByCerfrance\LlmApiLib\Llm;

$llm = new Llm($openAiProvider, $mistralProvider, $googleProvider);

// If OpenAI fails, Mistral is tried. If Mistral fails, Google is tried.
$response = $llm->chat('Hello');
```

### Capability-based filtering

Before attempting providers, `Llm` automatically filters them by required capabilities. If a message contains an image,
only providers with the `IMAGE` capability are tried. If a `JsonSchemaFormat` is used, only providers with `JSON_SCHEMA`
are tried.

### Strategy-based ordering

When a `SelectionStrategy` is set on the completion, providers are sorted by their score (based on `ModelInfo`
quality/cost tiers) before the failover sequence begins.

## Logging

The `chat()` method accepts an optional PSR-3 logger for per-call logging:

```php
use Psr\Log\LoggerInterface;

/** @var LoggerInterface $logger */
$response = $llm->chat($completion, logger: $logger);
```

The library logs:

- Provider selection and routing decisions
- Request initiation and completion metrics (tokens, cost, finish reason)
- Tool call counts and execution
- Retry attempts with wait times
- Failover transitions with error details

## Usage & Cost Tracking

### Token usage

Retrieve aggregated token usage across all calls:

```php
$usage = $llm->getUsage();
echo $usage->getPromptTokens();      // Total input tokens
echo $usage->getCompletionTokens();  // Total output tokens
echo $usage->getTotalTokens();       // Total tokens
```

### Cost tracking

Calculate monetary cost based on `ModelInfo` pricing:

```php
$cost = $llm->getCost();           // Total cost in dollars (4 decimal precision)
$cost = $llm->getCost(precision: 6); // Higher precision
```

Cost is computed as: `(promptTokens * inputCost / 1M) + (completionTokens * outputCost / 1M)`.

### Context window

Query the model's maximum context window size:

```php
$maxTokens = $llm->getMaxContextTokens(); // e.g. 128000, or null if undefined
```

When using multi-provider `Llm`, returns the minimum across all providers.

## Capabilities

This library supports a wide range of LLM capabilities, allowing developers to leverage advanced features such as
multimodal processing, structured output, and reasoning. The following table lists the supported capabilities along with
their descriptions.

| Capability      | Description (English)                                                                                                       |
|-----------------|-----------------------------------------------------------------------------------------------------------------------------|
| **text**        | Ability to read, process, and generate natural language text.                                                               |
| **image**       | Ability to interpret visual content from images.                                                                            |
| **ocr**         | Ability to extract textual content embedded within images (printed or handwritten).                                         |
| **document**    | Ability to process structured, often multi-page documents (e.g., PDFs), including visual layout and textual interpretation. |
| **audio**       | Ability to process and interpret speech or audio signals.                                                                   |
| **video**       | Ability to understand and analyze visual-temporal content from videos.                                                      |
| **reasoning**   | Ability to perform logical, analytical, or multi-step reasoning to derive conclusions.                                      |
| **json_output** | Ability to generate responses strictly formatted as valid JSON.                                                             |
| **json_schema** | Ability to generate responses that strictly follow a predefined JSON schema.                                                |
| **code**        | Ability to interpret, generate, or transform programming code.                                                              |
| **tools**       | Ability to call external tools or functions during inference.                                                               |
| **multimodal**  | Ability to combine and reason across multiple input types (e.g., text + image + audio + video).                             |

Each provider implementing the `LlmInterface` must declare its supported capabilities via the `getCapabilities()`
method. The `Llm` class automatically filters providers based on compatibility with the requested capabilities, ensuring
that only suitable providers are used for each request.
