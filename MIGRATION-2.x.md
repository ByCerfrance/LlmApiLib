# Migrating from 1.x to 2.x

The `2.x` branch separates provider protocols from execution. See [executable examples](examples/README.md) for the current support matrix and verification limits. No release tag has been published by this migration.

## Construction and execution

Providers no longer select or own a model. Bind a provider and a technical Model to a Chat executor:

```php
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Completion\Message\SystemMessage;
use ByCerfrance\LlmApiLib\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\OpenAi;

$chat = new Chat(new OpenAi($apiKey, $httpClient), new Model($modelName));
$request = new ChatRequest(new Conversation([
    new SystemMessage('Answer briefly.'),
    'Hello',
]));
$first = $chat->chat($request);
$second = $chat->chat($request->withConversation(
    $first->toConversation()->withNewMessage('Explain further.'),
));
$branch = $chat->chat($request->withConversation(
    $first->toConversation()->withNewMessage('Give a different example.'),
));
```

Conversation history is immutable. Keep the returned conversation rather than treating a response as a mutable request. Request options are separate and must be carried into later calls when needed. The provider's `complete()` performs one turn; Chat executes the tool loop.

## API replacements

| 1.x concept | 2.x replacement |
| --- | --- |
| LlmInterface and provider-owned model | ChatInterface + ChatProviderInterface + ModelInterface |
| Completion | ChatRequest + Conversation |
| CompletionResponse | ChatResponse with final result and all provider turns |
| LlmTool | ChatTool |
| Retry | RetryProvider for individual turns; explicit RetryChat for tool-free whole calls |
| Llm ordered provider fallback | FailoverProvider; ModelProvider for per-route model binding |
| Guard / FinishReasonGuard | GuardChat / FinishReasonGuardChat |
| Mutable provider usage counter | UsageCollector with ObservedProvider or ChatUsage |
| ModelInfo pricing | Model technical metadata + optional Pricing |
| Labels, quality/cost tiers, scores, selection strategies | Application-owned selection and role registration |

ImageGeneratorInterface is independent of ChatInterface. Applications can register chat and image executors in separate typed registries and attach their own role names. No library scoring or application-role contract is required.

## Options and model capabilities

Unset temperature/top-p now remain null and are omitted, letting the provider choose its defaults. Explicit zero is retained. Use `withTemperature(1)` and `withTopP(1)` only when the selected model supports them and those settings are intentional. Request settings override executor defaults.

Unknown model support preserves explicit parameters. Known incompatibilities fail under the default STRICT policy. OMIT_UNSUPPORTED must be explicitly chosen with logging; consult ParameterPolicy for the supported policy names. Context limits are metadata, not automatic token counting.

VISION denotes image input; IMAGE_OUTPUT denotes image generation. CODE, OCR and MULTIMODAL are not standalone capabilities. Use technical capabilities rather than subjective quality labels.

Google compatibility and GoogleNative are different protocols. Native Google uses v1beta GenerateContent and retains thought signatures in ProviderAssistantMessage. Keep its native history when editing or branching; changing the provider ID, model or protocol rejects that history.

Do not carry legacy manually injected `thinking_budget` into compatibility requests. Use a supported ReasoningEffort setting where the endpoint/model supports it. GoogleNative does not map reasoningEffort; supply documented native `thinkingConfig` through its generationConfig, matched to the model's supported thinking controls. There is no automatic translation between token budgets and thinking levels.

## Tools and recovery

Existing tool collections, MCP and OpenAPI collections feed ChatRequest directly. Arguments are validated with JSON Schema before callbacks execute. ToolCall requires a valid JSON object, not arbitrary JSON. Multiple calls execute sequentially.

Completed invocations are identified by ID plus tool/arguments and restored from conversation history. New IDs may legitimately repeat a tool. An injectable registry supports explicit recovery; this is not an exactly-once guarantee across crashes or external side effects.

RetryProvider and FailoverProvider retain the current turn and completed tool results. RetryChat rejects tool configurations/history to avoid silently repeating whole conversations with effects. Opaque native continuation must remain pinned to its provider.

## Accounting

Missing measurements and unknown prices are null, not zero. Input includes cached input; do not sum input, cached input and total tokens together. ChatResponse usage covers its own provider turns, not prior exchanges or delegated chats.

Choose either response-level ChatUsage or provider-attempt observation for a collector. To see failures and delegated consumption, place ObservedProvider inside retries and on actual routes. Compact collectors deliberately require exactly one ingestion per provider record. Price each model's consumption separately before combining costs; currencies remain separate.

## Third-party implementations

- ChatRequestInterface includes tools, error/iteration policy, output modalities, image configuration and immutable setters.
- ChatResponseInterface includes the final result, all turns, usage and continuation.
- ChatResultInterface includes parsed tool calls and actual provider/model metadata.
- ModelInterface exposes nullable capability knowledge, technical limits/constraints and optional getPricing().
- ChatProviderInterface executes one turn; ImageProviderInterface executes standalone image generation.
- Streaming interfaces must only be implemented when progressive delivery and cancellation actually work. Synchronous decorators do not implicitly forward streaming.

Always close ChatStream in a finally block when a consumer may stop early. A streaming guard cannot retract already emitted text. RetryStreamingProvider cannot replay a turn after its first emitted event.

For MCP SSE, inject a StreamingTransportInterface into HttpStreamable. JSON-RPC events are correlated, not concatenated. Notifications and shutdown still use PSR-18; supported protocol negotiation, session expiry and reconnect limits are documented in the examples.

## Verification and remaining release work

Deterministic tests cover chat, tools, images, Google edit/fork continuation, accounting, streaming and MCP SSE. Local HTTP tests verify progressive cURL delivery. External image and streaming APIs have not been credential-validated. Image streaming, background MCP GET/resumption, standalone image edits and automatic native continuation-token resumption are not implemented.

Historical v1 facades, completion executors, model scoring, mutable usage counters and their obsolete tests have been removed. Shared content, message, tool, payload and MCP/OpenAPI regressions remain; payload-builder and guard exception tests now target the v2 contracts. Release publication remains a separate action.
