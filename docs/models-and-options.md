# Models and options

[Back to documentation](README.md)

## Goal

Set generation options intentionally and describe the capabilities your application knows about. Use the [shared bootstrap](getting-started.md#shared-setup).

## Set options for one request

```php
<?php

declare(strict_types=1);

use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;

require __DIR__ . '/bootstrap.php';

$request = new ChatRequest(
    conversation: new Conversation(['Explain crop rotation briefly.']),
    maxTokens: 300,
);
$response = $chat->chat($request);
echo $response->getText() . PHP_EOL;
```

`maxTokens` limits generated output; it does not truncate your input history. Use a limit supported by your model.

For a model that supports sampling, a variant is:

```php
$request = $request->withTemperature(0)->withTopP(1);
```

These setters return new objects. Assign the result.

## Defaults and null

`new Chat($provider, $model, maxTokens: 300)` provides an executor default. A non-null request value overrides it. A null request value inherits the executor default, or is omitted if there is no default. Setting a request value back to null does not suppress a non-null executor default.

Unspecified temperature/top-p let the API choose defaults. Explicit `0` is retained. Low temperature is not a guarantee of identical answers.

## Declare known model metadata

This configuration-only variant describes an example model. Replace the facts with those of your actual model:

```php
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\Model;

$model = new Model(
    name: $modelName,
    capabilities: [Capability::TEXT, Capability::TOOLS, Capability::JSON_SCHEMA],
    maxOutputTokens: 4096,
    tunable: false,
);
```

An explicit capability list is exhaustive. In this example, VISION is unsupported. `capabilities: null` means support is unknown, while `[]` means none are supported. Metadata is supplied by your application; the library does not discover model facts from its name.

`tunable: false` rejects explicit sampling settings. `maxContextTokens` is descriptive metadata: the library does not count input tokens automatically. `unsupportedParameters` and `parameterValues` allow additional model-specific constraints.

Known incompatibilities fail by default. `ParameterPolicy::OMIT_UNSUPPORTED` is available on compatible providers, but requires a logger so omissions are observable. Missing content capabilities and excessive output limits remain errors.

## Request structured JSON

Add this variant to the first example for a model supporting JSON Schema:

```php
use ByCerfrance\LlmApiLib\Chat\ResponseFormat\JsonSchemaFormat;

$format = new JsonSchemaFormat(
    name: 'summary',
    schema: [
        'type' => 'object',
        'properties' => ['summary' => ['type' => 'string']],
        'required' => ['summary'],
        'additionalProperties' => false,
    ],
);
$response = $chat->chat($request->withResponseFormat($format));
$data = json_decode((string) $response->getText(), true, flags: JSON_THROW_ON_ERROR);
echo $data['summary'] . PHP_EOL;
```

The format is sent to the provider. It is not a guarantee of local response-schema validation; validate returned business data as needed.

Offline multimodal/JSON example: [examples/multimodal.php](../examples/multimodal.php). Image input requires VISION; generated images require IMAGE_OUTPUT. See [Images](images.md) and [Providers](providers.md).
