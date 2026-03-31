<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Mcp;

use ByCerfrance\LlmApiLib\Completion\Tool\AbstractTool;
use ByCerfrance\LlmApiLib\Mcp\OpenApi;
use ByCerfrance\LlmApiLib\Mcp\OpenApiTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpenApiTool::class)]
#[UsesClass(AbstractTool::class)]
class OpenApiToolTest extends TestCase
{
    public function testConstruction(): void
    {
        $server = $this->createMock(OpenApi::class);

        $tool = new OpenApiTool(
            name: 'getPetById',
            description: 'Find pet by ID',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'petId' => ['type' => 'integer', 'description' => 'ID of pet'],
                ],
                'required' => ['petId'],
            ],
            server: $server,
            method: 'GET',
            path: '/pets/{petId}',
            pathParams: [
                'petId' => [
                    'schema' => ['type' => 'integer'],
                    'description' => 'ID of pet',
                    'required' => true
                ]
            ],
            queryParams: [],
            headerParams: [],
            bodySchema: null,
        );

        $this->assertSame('getPetById', $tool->getName());
        $this->assertSame('Find pet by ID', $tool->getDescription());
        $this->assertSame('GET', $tool->getMethod());
        $this->assertSame('/pets/{petId}', $tool->getPath());
        $this->assertArrayHasKey('petId', $tool->getPathParams());
        $this->assertEmpty($tool->getQueryParams());
        $this->assertEmpty($tool->getHeaderParams());
        $this->assertNull($tool->getBodySchema());
    }

    public function testJsonSerialize(): void
    {
        $server = $this->createMock(OpenApi::class);

        $tool = new OpenApiTool(
            name: 'listPets',
            description: 'List all pets',
            parameters: [
                'type' => 'object',
                'properties' => ['limit' => ['type' => 'integer']],
            ],
            server: $server,
            method: 'GET',
            path: '/pets',
            pathParams: [],
            queryParams: ['limit' => ['schema' => ['type' => 'integer'], 'description' => '', 'required' => false]],
            headerParams: [],
            bodySchema: null,
        );

        $json = $tool->jsonSerialize();

        $this->assertSame('function', $json['type']);
        $this->assertSame('listPets', $json['function']['name']);
        $this->assertSame('List all pets', $json['function']['description']);
        $this->assertEquals(
            (object)['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]],
            $json['function']['parameters']
        );
    }

    public function testExecuteDelegatesToOpenApi(): void
    {
        $server = $this->createMock(OpenApi::class);
        $server->expects($this->once())
            ->method('callOperation')
            ->with('getPetById', ['petId' => 42])
            ->willReturn('{"id":42,"name":"Fido"}');

        $tool = new OpenApiTool(
            name: 'getPetById',
            description: 'Find pet by ID',
            parameters: ['type' => 'object'],
            server: $server,
            method: 'GET',
            path: '/pets/{petId}',
            pathParams: ['petId' => ['schema' => ['type' => 'integer'], 'description' => '', 'required' => true]],
            queryParams: [],
            headerParams: [],
            bodySchema: null,
        );

        $result = $tool->execute(['petId' => 42]);

        $this->assertSame('{"id":42,"name":"Fido"}', $result);
    }
}
