<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Mcp;

use ByCerfrance\LlmApiLib\Completion\Tool\AbstractTool;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollectionInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Mcp\AbstractServer;
use ByCerfrance\LlmApiLib\Mcp\OpenApi;
use ByCerfrance\LlmApiLib\Mcp\OpenApiTool;
use cebe\openapi\spec\OpenApi as OpenApiSpec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

#[CoversClass(OpenApi::class)]
#[CoversClass(AbstractServer::class)]
#[UsesClass(OpenApiTool::class)]
#[UsesClass(AbstractTool::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(ToolResult::class)]
class OpenApiTest extends TestCase
{
    /**
     * Create a cebe OpenAPI spec object for testing.
     */
    private function createSpec(array $paths = [], ?string $serverUrl = null): OpenApiSpec
    {
        $data = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => $paths,
        ];

        if (null !== $serverUrl) {
            $data['servers'] = [['url' => $serverUrl]];
        }

        return new OpenApiSpec($data);
    }

    /**
     * Create a mock PSR-7 Response.
     */
    private function createResponse(int $statusCode = 200, string $body = ''): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);
        $stream->method('__toString')->willReturn($body);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getReasonPhrase')->willReturn(match (true) {
            $statusCode === 200 => 'OK',
            $statusCode === 201 => 'Created',
            $statusCode === 400 => 'Bad Request',
            $statusCode === 404 => 'Not Found',
            $statusCode === 500 => 'Internal Server Error',
            default => 'Unknown',
        });
        $response->method('getBody')->willReturn($stream);

        return $response;
    }

    /**
     * Create a capturing mock PSR-18 client.
     */
    private function createCapturingClient(array $responses, array &$captured): ClientInterface
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(count($responses)))
            ->method('sendRequest')
            ->willReturnCallback(function (RequestInterface $req) use (&$responses, &$captured): ResponseInterface {
                $captured[] = $req;
                return array_shift($responses);
            });

        return $client;
    }

    // -----------------------------------------------------------------------
    // Tool discovery
    // -----------------------------------------------------------------------

    public function testDiscoverToolsFromSpec(): void
    {
        $spec = $this->createSpec([
            '/pets' => [
                'get' => [
                    'operationId' => 'listPets',
                    'summary' => 'List all pets',
                    'parameters' => [
                        ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => ['200' => ['description' => 'ok']],
                ],
                'post' => [
                    'operationId' => 'createPet',
                    'summary' => 'Create a pet',
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => ['name' => ['type' => 'string']],
                                    'required' => ['name'],
                                ],
                            ],
                        ],
                    ],
                    'responses' => ['201' => ['description' => 'created']],
                ],
            ],
        ], 'https://api.example.com');

        $client = $this->createMock(ClientInterface::class);
        $openApi = new OpenApi(spec: $spec, client: $client);

        $this->assertSame(2, $openApi->count());
        $this->assertTrue($openApi->has('listPets'));
        $this->assertTrue($openApi->has('createPet'));

        $listPets = $openApi->get('listPets');
        $this->assertInstanceOf(OpenApiTool::class, $listPets);
        $this->assertSame('listPets', $listPets->getName());
        $this->assertSame('List all pets', $listPets->getDescription());
        $this->assertSame('GET', $listPets->getMethod());
        $this->assertSame('/pets', $listPets->getPath());
    }

    public function testSkipsOperationsWithoutOperationId(): void
    {
        $spec = $this->createSpec([
            '/health' => [
                'get' => [
                    'summary' => 'Health check without operationId',
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
            '/pets' => [
                'get' => [
                    'operationId' => 'listPets',
                    'summary' => 'List pets',
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ]);

        $client = $this->createMock(ClientInterface::class);
        $openApi = new OpenApi(spec: $spec, client: $client);

        $this->assertSame(1, $openApi->count());
        $this->assertTrue($openApi->has('listPets'));
        $this->assertFalse($openApi->has('healthCheck'));
    }

    public function testMergesPathLevelAndOperationLevelParameters(): void
    {
        $spec = $this->createSpec([
            '/pets/{petId}' => [
                'parameters' => [
                    ['name' => 'petId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                ],
                'get' => [
                    'operationId' => 'getPet',
                    'summary' => 'Get pet',
                    'parameters' => [
                        ['name' => 'fields', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ],
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ]);

        $client = $this->createMock(ClientInterface::class);
        $openApi = new OpenApi(spec: $spec, client: $client);

        $tool = $openApi->get('getPet');
        $this->assertInstanceOf(OpenApiTool::class, $tool);
        $this->assertArrayHasKey('petId', $tool->getPathParams());
        $this->assertArrayHasKey('fields', $tool->getQueryParams());

        // The unified parameters should include both
        $params = $tool->getParameters();
        $this->assertArrayHasKey('petId', $params['properties']);
        $this->assertArrayHasKey('fields', $params['properties']);
        $this->assertContains('petId', $params['required']);
    }

    public function testHeaderParameters(): void
    {
        $spec = $this->createSpec([
            '/pets/{petId}' => [
                'delete' => [
                    'operationId' => 'deletePet',
                    'summary' => 'Delete pet',
                    'parameters' => [
                        ['name' => 'petId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ['name' => 'api_key', 'in' => 'header', 'schema' => ['type' => 'string']],
                    ],
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ]);

        $client = $this->createMock(ClientInterface::class);
        $openApi = new OpenApi(spec: $spec, client: $client);

        $tool = $openApi->get('deletePet');
        $this->assertInstanceOf(OpenApiTool::class, $tool);
        $this->assertArrayHasKey('api_key', $tool->getHeaderParams());
    }

    public function testRequestBodyWithoutJsonIgnored(): void
    {
        $spec = $this->createSpec([
            '/upload' => [
                'post' => [
                    'operationId' => 'uploadFile',
                    'summary' => 'Upload a file',
                    'requestBody' => [
                        'content' => [
                            'application/octet-stream' => [
                                'schema' => ['type' => 'string', 'format' => 'binary'],
                            ],
                        ],
                    ],
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ]);

        $client = $this->createMock(ClientInterface::class);
        $openApi = new OpenApi(spec: $spec, client: $client);

        $tool = $openApi->get('uploadFile');
        $this->assertInstanceOf(OpenApiTool::class, $tool);
        $this->assertNull($tool->getBodySchema());
    }

    // -----------------------------------------------------------------------
    // Base URL
    // -----------------------------------------------------------------------

    public function testBaseUrlFromSpec(): void
    {
        $captured = [];
        $client = $this->createCapturingClient(
            [$this->createResponse(body: '{"id":1}')],
            $captured,
        );

        $spec = $this->createSpec([
            '/pets' => [
                'get' => [
                    'operationId' => 'listPets',
                    'summary' => 'List',
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ], 'https://api.example.com/v3');

        $openApi = new OpenApi(spec: $spec, client: $client);
        $openApi->callOperation('listPets', []);

        $this->assertSame('https://api.example.com/v3/pets', (string)$captured[0]->getUri());
    }

    public function testBaseUrlOverride(): void
    {
        $captured = [];
        $client = $this->createCapturingClient(
            [$this->createResponse(body: '{}')],
            $captured,
        );

        $spec = $this->createSpec([
            '/pets' => [
                'get' => [
                    'operationId' => 'listPets',
                    'summary' => 'List',
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ], 'https://wrong.example.com');

        $openApi = new OpenApi(spec: $spec, client: $client, baseUrl: 'https://correct.example.com/v2');
        $openApi->callOperation('listPets', []);

        $this->assertSame('https://correct.example.com/v2/pets', (string)$captured[0]->getUri());
    }

    public function testBaseUrlTrailingSlashHandled(): void
    {
        $captured = [];
        $client = $this->createCapturingClient(
            [$this->createResponse(body: '{}')],
            $captured,
        );

        $spec = $this->createSpec([
            '/pets' => [
                'get' => [
                    'operationId' => 'listPets',
                    'summary' => 'List',
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ], 'https://api.example.com/v3/');

        $openApi = new OpenApi(spec: $spec, client: $client);
        $openApi->callOperation('listPets', []);

        $this->assertSame('https://api.example.com/v3/pets', (string)$captured[0]->getUri());
    }

    // -----------------------------------------------------------------------
    // callOperation() — request building
    // -----------------------------------------------------------------------

    public function testPathParameterSubstitution(): void
    {
        $captured = [];
        $client = $this->createCapturingClient(
            [$this->createResponse(body: '{"id":42}')],
            $captured,
        );

        $spec = $this->createSpec([
            '/pets/{petId}' => [
                'get' => [
                    'operationId' => 'getPet',
                    'summary' => 'Get pet',
                    'parameters' => [
                        ['name' => 'petId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ], 'https://api.example.com');

        $openApi = new OpenApi(spec: $spec, client: $client);
        $openApi->callOperation('getPet', ['petId' => 42]);

        $this->assertSame('https://api.example.com/pets/42', (string)$captured[0]->getUri());
        $this->assertSame('GET', $captured[0]->getMethod());
    }

    public function testQueryParameters(): void
    {
        $captured = [];
        $client = $this->createCapturingClient(
            [$this->createResponse(body: '[]')],
            $captured,
        );

        $spec = $this->createSpec([
            '/pets' => [
                'get' => [
                    'operationId' => 'listPets',
                    'summary' => 'List',
                    'parameters' => [
                        ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ], 'https://api.example.com');

        $openApi = new OpenApi(spec: $spec, client: $client);
        $openApi->callOperation('listPets', ['status' => 'available', 'limit' => 10]);

        $uri = (string)$captured[0]->getUri();
        $this->assertStringContainsString('status=available', $uri);
        $this->assertStringContainsString('limit=10', $uri);
    }

    public function testHeaderParametersSentInRequest(): void
    {
        $captured = [];
        $client = $this->createCapturingClient(
            [$this->createResponse(body: '{}')],
            $captured,
        );

        $spec = $this->createSpec([
            '/pets/{petId}' => [
                'delete' => [
                    'operationId' => 'deletePet',
                    'summary' => 'Delete pet',
                    'parameters' => [
                        ['name' => 'petId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ['name' => 'X-Api-Key', 'in' => 'header', 'schema' => ['type' => 'string']],
                    ],
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ], 'https://api.example.com');

        $openApi = new OpenApi(spec: $spec, client: $client);
        $openApi->callOperation('deletePet', ['petId' => 1, 'X-Api-Key' => 'secret']);

        $this->assertSame(['secret'], $captured[0]->getHeader('X-Api-Key'));
    }

    public function testPostWithJsonBody(): void
    {
        $captured = [];
        $client = $this->createCapturingClient(
            [$this->createResponse(statusCode: 201, body: '{"id":1,"name":"Fido"}')],
            $captured,
        );

        $spec = $this->createSpec([
            '/pets' => [
                'post' => [
                    'operationId' => 'createPet',
                    'summary' => 'Create pet',
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'status' => ['type' => 'string'],
                                    ],
                                    'required' => ['name'],
                                ],
                            ],
                        ],
                    ],
                    'responses' => ['201' => ['description' => 'created']],
                ],
            ],
        ], 'https://api.example.com');

        $openApi = new OpenApi(spec: $spec, client: $client);
        $result = $openApi->callOperation('createPet', ['name' => 'Fido', 'status' => 'available']);

        $this->assertSame('POST', $captured[0]->getMethod());
        $this->assertSame(['application/json'], $captured[0]->getHeader('Content-Type'));
        $body = json_decode((string)$captured[0]->getBody(), true);
        $this->assertSame('Fido', $body['name']);
        $this->assertSame('available', $body['status']);
        $this->assertSame('{"id":1,"name":"Fido"}', $result);
    }

    public function testGetWithoutBody(): void
    {
        $captured = [];
        $client = $this->createCapturingClient(
            [$this->createResponse(body: '[]')],
            $captured,
        );

        $spec = $this->createSpec([
            '/pets' => [
                'get' => [
                    'operationId' => 'listPets',
                    'summary' => 'List',
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ], 'https://api.example.com');

        $openApi = new OpenApi(spec: $spec, client: $client);
        $openApi->callOperation('listPets', []);

        // GET should not have Content-Type header
        $this->assertEmpty($captured[0]->getHeader('Content-Type'));
    }

    public function testCustomHeadersSentOnEveryRequest(): void
    {
        $captured = [];
        $client = $this->createCapturingClient(
            [$this->createResponse(body: '{}')],
            $captured,
        );

        $spec = $this->createSpec([
            '/pets' => [
                'get' => [
                    'operationId' => 'listPets',
                    'summary' => 'List',
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ], 'https://api.example.com');

        $openApi = new OpenApi(
            spec: $spec,
            client: $client,
            headers: ['Authorization' => 'Bearer my-token', 'X-Custom' => 'value'],
        );
        $openApi->callOperation('listPets', []);

        $this->assertSame(['Bearer my-token'], $captured[0]->getHeader('Authorization'));
        $this->assertSame(['value'], $captured[0]->getHeader('X-Custom'));
    }

    public function testMissingPathParameterThrowsException(): void
    {
        $spec = $this->createSpec([
            '/pets/{petId}' => [
                'get' => [
                    'operationId' => 'getPet',
                    'summary' => 'Get pet',
                    'parameters' => [
                        ['name' => 'petId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ], 'https://api.example.com');

        $client = $this->createMock(ClientInterface::class);
        $openApi = new OpenApi(spec: $spec, client: $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required path parameter: petId');
        $openApi->callOperation('getPet', []);
    }

    // -----------------------------------------------------------------------
    // Error handling
    // -----------------------------------------------------------------------

    public function testHttpErrorThrowsException(): void
    {
        $captured = [];
        $client = $this->createCapturingClient(
            [$this->createResponse(statusCode: 500)],
            $captured,
        );

        $spec = $this->createSpec([
            '/pets' => [
                'get' => [
                    'operationId' => 'listPets',
                    'summary' => 'List',
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ], 'https://api.example.com');

        $openApi = new OpenApi(spec: $spec, client: $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAPI call "listPets" failed (500 Internal Server Error)');
        $openApi->callOperation('listPets', []);
    }

    public function testGetNonExistentToolThrowsException(): void
    {
        $spec = $this->createSpec([
            '/pets' => [
                'get' => [
                    'operationId' => 'listPets',
                    'summary' => 'List',
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ]);

        $client = $this->createMock(ClientInterface::class);
        $openApi = new OpenApi(spec: $spec, client: $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tool "nonexistent" not found in collection');
        $openApi->get('nonexistent');
    }

    // -----------------------------------------------------------------------
    // execute() (ToolCollectionInterface)
    // -----------------------------------------------------------------------

    public function testExecuteReturnsToolResult(): void
    {
        $captured = [];
        $client = $this->createCapturingClient(
            [$this->createResponse(body: '{"id":42}')],
            $captured,
        );

        $spec = $this->createSpec([
            '/pets/{petId}' => [
                'get' => [
                    'operationId' => 'getPet',
                    'summary' => 'Get pet',
                    'parameters' => [
                        ['name' => 'petId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => ['200' => ['description' => 'ok']],
                ],
            ],
        ], 'https://api.example.com');

        $openApi = new OpenApi(spec: $spec, client: $client);

        $toolCall = new ToolCall(id: 'call_123', name: 'getPet', arguments: ['petId' => 42]);
        $result = $openApi->execute($toolCall);

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertSame('call_123', $result->getToolCallId());
        $this->assertSame('{"id":42}', (string)$result->getContent());
    }

    // -----------------------------------------------------------------------
    // ToolCollectionInterface compliance
    // -----------------------------------------------------------------------

    public function testImplementsToolCollectionInterface(): void
    {
        $spec = $this->createSpec([]);
        $client = $this->createMock(ClientInterface::class);
        $openApi = new OpenApi(spec: $spec, client: $client);

        $this->assertInstanceOf(ToolCollectionInterface::class, $openApi);
    }

    public function testIteratorAndJsonSerialize(): void
    {
        $spec = $this->createSpec([
            '/a' => ['get' => ['operationId' => 'opA', 'summary' => 'A', 'responses' => ['200' => ['description' => 'ok']]]],
            '/b' => ['post' => ['operationId' => 'opB', 'summary' => 'B', 'responses' => ['200' => ['description' => 'ok']]]],
        ]);

        $client = $this->createMock(ClientInterface::class);
        $openApi = new OpenApi(spec: $spec, client: $client);

        $tools = iterator_to_array($openApi->getIterator());
        $this->assertCount(2, $tools);

        $serialized = $openApi->jsonSerialize();
        $this->assertCount(2, $serialized);
    }
}
