<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Mcp;

use ByCerfrance\LlmApiLib\Completion\Tool\AbstractTool;
use Override;

/**
 * A tool discovered from an OpenAPI specification.
 *
 * Carries REST metadata (HTTP method, path, parameter locations) and
 * delegates execution to the parent OpenApi server via callOperation().
 */
readonly class OpenApiTool extends AbstractTool
{
    /**
     * @param string $name operationId from the OpenAPI spec
     * @param string $description summary or description from the OpenAPI spec
     * @param array $parameters Unified JSON Schema for all parameters (path, query, header, body merged)
     * @param OpenApi $server The OpenApi server that owns this tool
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $path URL path template (e.g. /pets/{petId})
     * @param array<string, array> $pathParams Path parameters [{name => {schema, description, required}}]
     * @param array<string, array> $queryParams Query parameters [{name => {schema, description, required}}]
     * @param array<string, array> $headerParams Header parameters [{name => {schema, description, required}}]
     * @param array|null $bodySchema JSON Schema for the request body, or null if no JSON body
     */
    public function __construct(
        string $name,
        string $description,
        array $parameters,
        private OpenApi $server,
        private string $method,
        private string $path,
        private array $pathParams,
        private array $queryParams,
        private array $headerParams,
        private ?array $bodySchema,
    ) {
        parent::__construct($name, $description, $parameters);
    }

    #[Override]
    public function execute(array $arguments): string
    {
        return $this->server->callOperation($this->getName(), $arguments);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, array>
     */
    public function getPathParams(): array
    {
        return $this->pathParams;
    }

    /**
     * @return array<string, array>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @return array<string, array>
     */
    public function getHeaderParams(): array
    {
        return $this->headerParams;
    }

    public function getBodySchema(): ?array
    {
        return $this->bodySchema;
    }
}
