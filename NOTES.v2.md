# llm-api-lib — V2 Notes

Living document for preparing version `2.0.0`. The items below are planned work; this document is not a changelog of completed changes.

## 1. Optional sampling parameters

### Goal

Let each provider and model apply its own defaults when the caller does not configure sampling parameters.

### Planned changes

- Default `temperature` and `top_p` to `null` in `Completion`.
- Update getters to return `int|float|null`.
- Accept `null` in `withTemperature()` and `withTopP()` to reset a setting.
- Update `CompletionInterface`, `Completion`, and `CompletionResponse`.
- Omit `null` values from the JSON payload while preserving explicit values, including `0`.
- Apply the same convention to `top_k` if a dedicated field is introduced later; adding it is outside the scope of this work.

### Breaking changes

- Getters become nullable.
- Third-party interface implementations must update their signatures.
- Requests without explicit configuration use provider defaults instead of always sending `temperature = 1` and `top_p = 1`.

## 2. Google / Gemini compatibility

### Background

Google has announced that upcoming models will reject `thinking_budget` and the sampling parameters `temperature`, `top_p`, and `top_k`. The library's Google provider uses the OpenAI-compatible endpoint.

### Planned changes

- Always strip `temperature`, `top_p`, and `top_k` from the Google provider's final payload, after merging `extraBody`.
- Target only root-level generation fields, without removing properties with the same names from tool or structured output schemas.
- Preserve `reasoning_effort`, which the library already uses and Google's OpenAI-compatible API supports.
- Retain the model-specific filtering mechanism for other applicable configurations.

### Implications to document

- Google filtering also applies to older models that still accept these settings and to moving aliases such as `gemini-flash-latest`.
- Explicitly configured values, including those supplied through `extraBody`, are ignored for Google.
- The inspected code does not generate `thinking_budget` directly. Applications injecting it through `extraBody` must migrate their configuration for affected models to an explicit thinking level or `reasoning_effort`.
- Do not automatically convert a numeric budget into a thinking level by merely renaming the field, or combine `reasoning_effort` with a competing Google thinking configuration.

Reference: https://ai.google.dev/gemini-api/docs/openai#thinking

## 3. Tests and validation

- Verify that unconfigured parameters are absent from the JSON payload.
- Verify that explicit values, including `0`, are preserved unless applicable filtering removes them.
- Verify resetting to `null` through the `with*()` methods and `CompletionResponse` delegation.
- Verify Google's final HTTP payload for default parameters, explicit values, and values injected through `extraBody`.
- Verify that `reasoning_effort` and properties with matching names in nested schemas are preserved.
- Update existing tests for the new defaults and comply with PHPUnit coverage metadata requirements.
- Run `composer run test` and `composer run analyse`.

## 4. Documentation and release

- Prepare the `2.0.0` major release.
- Document breaking changes in the changelog and a migration guide.
- Explain how to adapt third-party implementations and code consuming the getters.
- To explicitly preserve the previous settings with compatible providers:

```php
$completion
    ->withTemperature(1)
    ->withTopP(1);
```

## 5. Other V2 topics

To be expanded as further decisions are made.
