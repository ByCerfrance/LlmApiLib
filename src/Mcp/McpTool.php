<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Mcp;

use ByCerfrance\LlmApiLib\Completion\Tool\AbstractTool;
use Override;

/**
 * A tool discovered from an MCP server.
 *
 * Acts as a value object carrying tool metadata (name, description, inputSchema)
 * and delegates execution to the parent McpServer via tools/call.
 */
readonly class McpTool extends AbstractTool
{
    /**
     * @param string $name Tool name from MCP tools/list
     * @param string $description Tool description from MCP tools/list
     * @param array $parameters JSON Schema (inputSchema) from MCP tools/list
     * @param McpServer $server The MCP server that owns this tool
     */
    public function __construct(
        string $name,
        string $description,
        array $parameters,
        private McpServer $server,
    ) {
        parent::__construct($name, $description, $parameters);
    }

    #[Override]
    public function execute(array $arguments): string
    {
        return $this->server->callTool($this->getName(), $arguments);
    }
}
