# V2 chat preview

The `2.x` branch builds the new API directly in its final domain namespaces. Providers are migrated incrementally: OpenAI now uses the separated provider/executor contracts, while the remaining providers still use the old API until their migration.

Run a deterministic conversation without credentials or network traffic:

```shell
php examples/chat.php
```

The example sends two exchanges with a system prompt, then branches from the first response. Expected request history lengths are 2, 4, 4; final histories are 2 (original), 3 (first response), 5 (second response), 5 (branch).

For a real request, replace the example transport with a PSR-18 HTTP client, supply your OpenAI key to `ByCerfrance\LlmApiLib\Provider\OpenAi`, and configure an available model through `ByCerfrance\LlmApiLib\Model\Model`. The provider sends requests to OpenAI's chat completions endpoint. This example's fixture is not a live provider validation.

## Contracts available in milestone 1

- `Chat` binds the provider and model. `chat()` accepts a string or a `ChatRequestInterface`.
- `Conversation` retains ordered messages and supports immutable append. Supplied custom messages and nested content must themselves be immutable; do not insert v1's mutable `Choices` container.
- `ChatRequest` supports `maxTokens`, `temperature`, and `topP`, with immutable setters and conversation replacement.
- Request values override Chat constructor defaults. Null means unspecified: inherit executor defaults, otherwise omit the field. Explicit zero is preserved for sampling options.
- `ChatResponse::toConversation()` returns the complete history, while `getResult()` exposes the final message, finish reason, provider ID, reported model, and HTTP request ID.
- `getUsage()` returns immutable `TokenUsageInterface` measurements per call. Missing provider metrics remain null, not zero. No cumulative counter is attached to Chat or the provider. The old mutable `Usage` remains for providers not yet migrated.
- Provider extensions cannot replace the model/history, enable streaming/tools/multiple choices, or overwrite configured fields.

## Current scope

This first slice handles a single text assistant result. Multiple output choices, structured/multimodal output, tool execution, retry/failover, native Google, cumulative usage, pricing, and streaming are delivered by later milestones. Unsupported tool/multimodal responses fail explicitly rather than silently discarding their contents. Technical model capabilities/limits are added in milestone 3.

Errors currently use SPL exceptions. Normalized retry categories arrive with the resilience milestone. Raw provider error bodies are not included in exception messages or logs.
