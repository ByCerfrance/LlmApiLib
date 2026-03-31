<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Mcp;

use Berlioz\Http\Message\Request;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use cebe\openapi\spec\OpenApi as OpenApiSpec;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Schema;
use Override;
use Psr\Http\Client\ClientInterface;
use RuntimeException;

/**
 * OpenAPI client that discovers tools from an OpenAPI specification and executes REST calls.
 *
 * Requires the `devizzent/cebe-php-openapi` package to be installed.
 *
 * Usage:
 *
 *     use cebe\openapi\Reader;
 *     use cebe\openapi\ReferenceContext;
 *
 *     $spec = Reader::readFromJsonFile('https://api.example.com/openapi.json');
 *     $spec->resolveReferences(new ReferenceContext($spec, 'https://api.example.com/openapi.json'));
 *
 *     $openApi = new OpenApi(
 *         spec: $spec,
 *         client: $httpClient,
 *         baseUrl: 'https://api.example.com/v3',
 *     );
 *
 *     // Use directly or with filtering:
 *     $completion->withTools($openApi);
 *     $completion->withTools(new FilteredToolCollection($openApi, ['getPetById', '!deletePet']));
 *
 * @see https://spec.openapis.org/oas/v3.1.0
 */
class OpenApi extends AbstractServer
{
    private string $resolvedBaseUrl;

    /**
     * @param OpenApiSpec $spec Parsed and $ref-resolved OpenAPI specification (via cebe/php-openapi)
     * @param ClientInterface $client PSR-18 HTTP client for executing API calls
     * @param array<string, string> $headers Additional HTTP headers (e.g. Authorization)
     * @param string|null $baseUrl Override for servers[0].url (useful for relative URLs or custom environments)
     */
    public function __construct(
        private readonly OpenApiSpec $spec,
        private readonly ClientInterface $client,
        private readonly array $headers = [],
        private readonly ?string $baseUrl = null,
    ) {
    }

    /**
     * Call an API operation by its operationId.
     *
     * Dispatches arguments to path parameters, query parameters, header parameters
     * and request body based on the OpenAPI operation metadata.
     *
     * @param string $operationId The operationId to call
     * @param array $arguments Arguments from the LLM tool call
     *
     * @return string Response body as string
     * @throws RuntimeException On HTTP error or missing required parameters
     */
    public function callOperation(string $operationId, array $arguments): string
    {
        $this->ensureInitialized();

        $tool = $this->get($operationId);
        if (!$tool instanceof OpenApiTool) {
            throw new RuntimeException(sprintf('Tool "%s" is not an OpenAPI tool', $operationId));
        }

        // 1. Path parameter substitution
        $path = $tool->getPath();
        foreach ($tool->getPathParams() as $name => $param) {
            if (!array_key_exists($name, $arguments)) {
                throw new RuntimeException(sprintf('Missing required path parameter: %s', $name));
            }
            $path = str_replace('{' . $name . '}', rawurlencode((string)$arguments[$name]), $path);
            unset($arguments[$name]);
        }

        // 2. Query parameters
        $query = [];
        foreach ($tool->getQueryParams() as $name => $param) {
            if (array_key_exists($name, $arguments)) {
                $query[$name] = $arguments[$name];
                unset($arguments[$name]);
            }
        }

        // 3. Header parameters
        $extraHeaders = [];
        foreach ($tool->getHeaderParams() as $name => $param) {
            if (array_key_exists($name, $arguments)) {
                $extraHeaders[$name] = (string)$arguments[$name];
                unset($arguments[$name]);
            }
        }

        // 4. Build URL
        $url = rtrim($this->resolvedBaseUrl, '/') . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        // 5. Build body (remaining arguments go to body for POST/PUT/PATCH)
        $body = null;
        if (in_array($tool->getMethod(), ['POST', 'PUT', 'PATCH'], true) && !empty($arguments)) {
            $body = json_encode($arguments, JSON_THROW_ON_ERROR);
            $extraHeaders['Content-Type'] = 'application/json';
        }

        // 6. Send HTTP request
        $request = new Request(
            method: $tool->getMethod(),
            uri: $url,
            body: $body,
            headers: array_merge($this->headers, $extraHeaders),
        );

        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException(
                sprintf(
                    'OpenAPI call "%s" failed (%d %s)',
                    $operationId,
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                )
            );
        }

        return $response->getBody()->getContents();
    }

    #[Override]
    public function execute(ToolCall $toolCall): ToolResult
    {
        $result = $this->callOperation($toolCall->name, $toolCall->arguments);

        return new ToolResult(
            toolCallId: $toolCall->id,
            content: $result,
        );
    }

    #[Override]
    protected function doInitialize(): void
    {
        $this->resolvedBaseUrl = $this->baseUrl ?? $this->spec->servers[0]->url ?? '';
        $this->parseOperations();
    }

    /**
     * Iterate the OpenAPI spec and create OpenApiTool instances for each operation.
     */
    private function parseOperations(): void
    {
        $methods = ['get', 'post', 'put', 'patch', 'delete'];

        foreach ($this->spec->paths as $path => $pathItem) {
            if (!$pathItem instanceof PathItem) {
                continue;
            }

            $pathLevelParams = $pathItem->parameters ?? [];

            foreach ($methods as $method) {
                /** @var Operation|null $operation */
                $operation = $pathItem->$method;
                if (null === $operation) {
                    continue;
                }

                $operationId = $operation->operationId;
                if (null === $operationId || '' === $operationId) {
                    continue;
                }

                // Merge path-level + operation-level parameters (operation overrides path-level by name+in)
                $mergedParams = $this->mergeParameters($pathLevelParams, $operation->parameters ?? []);

                // Classify by location
                $pathParams = [];
                $queryParams = [];
                $headerParams = [];

                foreach ($mergedParams as $param) {
                    $paramData = [
                        'schema' => $this->schemaToArray($param->schema),
                        'description' => $param->description ?? '',
                        'required' => $param->required ?? false,
                    ];

                    match ($param->in) {
                        'path' => $pathParams[$param->name] = $paramData,
                        'query' => $queryParams[$param->name] = $paramData,
                        'header' => $headerParams[$param->name] = $paramData,
                        default => null,
                    };
                }

                // Body schema (application/json only)
                $bodySchema = null;
                $requestBody = $operation->requestBody;
                if (null !== $requestBody) {
                    $jsonContent = $requestBody->content['application/json'] ?? null;
                    if (null !== $jsonContent && null !== $jsonContent->schema) {
                        $bodySchema = $this->schemaToArray($jsonContent->schema);
                    }
                }

                // Build unified JSON Schema for the LLM
                $toolParams = $this->buildToolParameters($pathParams, $queryParams, $headerParams, $bodySchema);

                $description = $operation->summary ?? $operation->description ?? '';

                $this->tools[$operationId] = new OpenApiTool(
                    name: $operationId,
                    description: $description,
                    parameters: $toolParams,
                    server: $this,
                    method: strtoupper($method),
                    path: (string)$path,
                    pathParams: $pathParams,
                    queryParams: $queryParams,
                    headerParams: $headerParams,
                    bodySchema: $bodySchema,
                );
            }
        }
    }

    /**
     * Merge path-level and operation-level parameters.
     *
     * Operation-level parameters override path-level parameters with the same name and location.
     *
     * @param Parameter[] $pathLevel
     * @param Parameter[] $operationLevel
     *
     * @return Parameter[]
     */
    private function mergeParameters(array $pathLevel, array $operationLevel): array
    {
        $merged = [];

        foreach ($pathLevel as $param) {
            $key = $param->name . ':' . $param->in;
            $merged[$key] = $param;
        }

        foreach ($operationLevel as $param) {
            $key = $param->name . ':' . $param->in;
            $merged[$key] = $param;
        }

        return array_values($merged);
    }

    /**
     * Build a unified JSON Schema for the LLM from all parameter sources.
     *
     * @param array<string, array> $pathParams
     * @param array<string, array> $queryParams
     * @param array<string, array> $headerParams
     * @param array|null $bodySchema
     *
     * @return array JSON Schema
     */
    private function buildToolParameters(
        array $pathParams,
        array $queryParams,
        array $headerParams,
        ?array $bodySchema,
    ): array {
        $properties = [];
        $required = [];

        foreach ([$pathParams, $queryParams, $headerParams] as $params) {
            foreach ($params as $name => $param) {
                $property = $param['schema'];
                if (!empty($param['description'])) {
                    $property['description'] = $param['description'];
                }
                $properties[$name] = $property;

                if (!empty($param['required'])) {
                    $required[] = $name;
                }
            }
        }

        // Merge body schema properties
        if (null !== $bodySchema && isset($bodySchema['properties'])) {
            foreach ($bodySchema['properties'] as $name => $schema) {
                $properties[$name] = $schema;
            }
            if (isset($bodySchema['required'])) {
                $required = [...$required, ...$bodySchema['required']];
            }
        }

        $result = ['type' => 'object'];

        if (!empty($properties)) {
            $result['properties'] = $properties;
        }

        if (!empty($required)) {
            $result['required'] = array_values(array_unique($required));
        }

        return $result;
    }

    /**
     * Convert a cebe Schema object to a plain PHP array.
     *
     * @param Schema|null $schema
     *
     * @return array
     */
    private function schemaToArray(?Schema $schema): array
    {
        if (null === $schema) {
            return ['type' => 'string'];
        }

        $data = $schema->getSerializableData();

        return json_decode(json_encode($data), true);
    }
}
