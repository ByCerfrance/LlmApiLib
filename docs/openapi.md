# OpenAPI tools

[Back to documentation](README.md)

## Goal

Expose REST operations as tools using an OpenAPI document. Unlike MCP, this adapter calls your REST API directly; it does not need an MCP server.

## Install the parser

In addition to the [shared setup](getting-started.md):

```shell
composer require devizzent/cebe-php-openapi
```

Set `OPENAPI_SPEC` to an absolute path or URL for your JSON OpenAPI document, `REST_BASE_URL` to the API base URL, and `REST_OPERATION` to an operationId in the document. Set `REST_PROMPT` to a task that operation can perform. `REST_TOKEN` supplies optional bearer authentication for API calls.

## Discover and expose an operation

```php
<?php

declare(strict_types=1);

use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Tool\FilteredToolCollection;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\OpenApi\OpenApi;
use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;

require __DIR__ . '/bootstrap.php';

$specLocation = getenv('OPENAPI_SPEC') ?: throw new RuntimeException('Set OPENAPI_SPEC.');
$baseUrl = getenv('REST_BASE_URL') ?: throw new RuntimeException('Set REST_BASE_URL.');
$operation = getenv('REST_OPERATION') ?: throw new RuntimeException('Set REST_OPERATION.');
$prompt = getenv('REST_PROMPT') ?: throw new RuntimeException('Set REST_PROMPT.');
$token = getenv('REST_TOKEN');

$spec = Reader::readFromJsonFile($specLocation);
$spec->resolveReferences(new ReferenceContext($spec, $specLocation));
$api = new OpenApi(
    spec: $spec,
    client: $client,
    headers: $token ? ['Authorization' => 'Bearer ' . $token] : [],
    baseUrl: $baseUrl,
);
$tools = new FilteredToolCollection($api, [$operation]);
$response = $chat->chat(new ChatRequest(new Conversation([$prompt]), tools: $tools));
echo (string) $response->getResult()->getMessage()->getContent() . PHP_EOL;
```

This makes real requests. For an API exposing `getField` at `/fields/{id}`, a suitable prompt might be “Use getField to describe field 42.” The model receives the operation's argument schema and its HTTP result, then writes an answer.

The adapter expects a parsed, reference-resolved specification. `baseUrl` overrides the first server URL, which is useful for relative URLs or different deployment environments. Headers configured here apply to API calls, not to downloading the specification through Reader.

## Understand the mapping

- Tool names come from operation IDs. Operations without an operationId are skipped.
- Path arguments fill URL placeholders; query and header arguments go to their corresponding locations.
- Remaining arguments become a JSON body for POST, PUT and PATCH.
- The HTTP response body becomes the tool result. HTTP errors raise an exception, handled according to the ChatRequest tool error policy.

This is an operation-to-tool adapter, not a complete implementation of every OpenAPI serialization or authentication scheme. Configure your required headers explicitly and check the generated schema for your API's operations.

## Try an operation without a model

As a variant after constructing `$api`, call a known operation directly:

```php
// Replace the ID and arguments with an operation from your specification.
$body = $api->callOperation('getField', ['id' => 42]);
echo $body . PHP_EOL;
```

This helps separate REST integration problems from model/tool selection. Direct calls do not run Chat's JSON Schema argument validator.

There is no standalone offline OpenAPI script. Deterministic integration coverage is in [ToolsChatTest](../tests/Chat/ToolsChatTest.php); [examples/tools.php](../examples/tools.php) demonstrates the same Chat tool loop without a REST server.

Related: [Tools](tools.md), [MCP](mcp.md), [Providers](providers.md).
