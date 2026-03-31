<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Completion\Tool;

use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\Tool\AbstractTool;
use ByCerfrance\LlmApiLib\Completion\Tool\LlmTool;
use ByCerfrance\LlmApiLib\LlmInterface;
use Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LlmTool::class)]
#[CoversClass(AbstractTool::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Message::class)]
#[UsesClass(TextContent::class)]
class LlmToolTest extends TestCase
{
    private function createMockLlm(string $responseText): LlmInterface
    {
        $content = new TextContent($responseText);

        $message = $this->createMock(MessageInterface::class);
        $message->method('getContent')->willReturn($content);

        $response = $this->createMock(CompletionResponseInterface::class);
        $response->method('getLastMessage')->willReturn($message);

        $llm = $this->createMock(LlmInterface::class);
        $llm->method('chat')->willReturn($response);

        return $llm;
    }

    public function testConstruction(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $tool = new LlmTool(
            name: 'extract_invoice',
            description: 'Extract invoice data',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'content' => ['type' => 'string'],
                ],
                'required' => ['content'],
            ],
            llm: $llm,
            promptBuilder: fn(array $args) => new Completion([
                new Message(new TextContent($args['content']), RoleEnum::USER),
            ]),
        );

        $this->assertSame('extract_invoice', $tool->getName());
        $this->assertSame('Extract invoice data', $tool->getDescription());
        $this->assertSame(
            [
                'type' => 'object',
                'properties' => ['content' => ['type' => 'string']],
                'required' => ['content'],
            ],
            $tool->getParameters()
        );
        $this->assertSame($llm, $tool->getLlm());
    }

    public function testJsonSerialize(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $tool = new LlmTool(
            name: 'search',
            description: 'Search for information',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string'],
                ],
            ],
            llm: $llm,
            promptBuilder: fn(array $args) => new Completion([]),
        );

        $json = $tool->jsonSerialize();

        $this->assertSame('function', $json['type']);
        $this->assertSame('search', $json['function']['name']);
        $this->assertSame('Search for information', $json['function']['description']);
        $this->assertEquals(
            (object)['type' => 'object', 'properties' => ['query' => ['type' => 'string']]],
            $json['function']['parameters']
        );
    }

    public function testExecuteArrayMode(): void
    {
        $llm = $this->createMockLlm('{"total": 1500}');

        $receivedArgs = null;
        $tool = new LlmTool(
            name: 'extract',
            description: 'Extract data',
            parameters: ['type' => 'object'],
            llm: $llm,
            promptBuilder: function (array $args) use (&$receivedArgs) {
                $receivedArgs = $args;

                return new Completion([
                    new Message(new TextContent($args['content']), RoleEnum::USER),
                ]);
            },
        );

        $result = $tool->execute(['content' => 'Invoice #123', 'extra' => 'data']);

        $this->assertSame('{"total": 1500}', $result);
        $this->assertSame(['content' => 'Invoice #123', 'extra' => 'data'], $receivedArgs);
    }

    public function testExecuteTypedMode(): void
    {
        $llm = $this->createMockLlm('{"total": 1500}');

        $receivedContent = null;
        $receivedFormat = null;
        $tool = new LlmTool(
            name: 'extract',
            description: 'Extract data',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'content' => ['type' => 'string'],
                    'format' => ['type' => 'string'],
                ],
            ],
            llm: $llm,
            promptBuilder: function (string $content, string $format) use (&$receivedContent, &$receivedFormat) {
                $receivedContent = $content;
                $receivedFormat = $format;

                return new Completion([
                    new Message(new TextContent($content), RoleEnum::USER),
                ]);
            },
        );

        $result = $tool->execute(['content' => 'Invoice #123', 'format' => 'json']);

        $this->assertSame('{"total": 1500}', $result);
        $this->assertSame('Invoice #123', $receivedContent);
        $this->assertSame('json', $receivedFormat);
    }

    public function testExecuteTypedModeWithOptionalParameter(): void
    {
        $llm = $this->createMockLlm('extracted data');

        $receivedFormat = null;
        $tool = new LlmTool(
            name: 'extract',
            description: 'Extract data',
            parameters: ['type' => 'object'],
            llm: $llm,
            promptBuilder: function (string $content, string $format = 'json') use (&$receivedFormat) {
                $receivedFormat = $format;

                return new Completion([
                    new Message(new TextContent($content), RoleEnum::USER),
                ]);
            },
        );

        // Without optional parameter
        $tool->execute(['content' => 'Invoice #123']);
        $this->assertSame('json', $receivedFormat);

        // With optional parameter
        $tool->execute(['content' => 'Invoice #123', 'format' => 'xml']);
        $this->assertSame('xml', $receivedFormat);
    }

    public function testExecuteTypedModeFiltersExtraArguments(): void
    {
        $llm = $this->createMockLlm('result');

        $receivedContent = null;
        $tool = new LlmTool(
            name: 'extract',
            description: 'Extract data',
            parameters: ['type' => 'object'],
            llm: $llm,
            promptBuilder: function (string $content) use (&$receivedContent) {
                $receivedContent = $content;

                return new Completion([
                    new Message(new TextContent($content), RoleEnum::USER),
                ]);
            },
        );

        // Extra arguments should be silently filtered out
        $result = $tool->execute(['content' => 'Invoice', 'unknown_param' => 'ignored', 'another' => 42]);

        $this->assertSame('result', $result);
        $this->assertSame('Invoice', $receivedContent);
    }

    public function testExecuteTypedModeWithMissingRequiredParameter(): void
    {
        $llm = $this->createMockLlm('result');

        $tool = new LlmTool(
            name: 'extract',
            description: 'Extract data',
            parameters: ['type' => 'object'],
            llm: $llm,
            promptBuilder: fn(string $content, string $format) => new Completion([]),
        );

        $this->expectException(Error::class);

        $tool->execute(['content' => 'Invoice']); // Missing 'format'
    }

    public function testExecuteCallsLlmWithBuiltCompletion(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $content = new TextContent('response');
        $message = $this->createMock(MessageInterface::class);
        $message->method('getContent')->willReturn($content);

        $response = $this->createMock(CompletionResponseInterface::class);
        $response->method('getLastMessage')->willReturn($message);

        $llm->expects($this->once())
            ->method('chat')
            ->with(
                $this->callback(function (CompletionInterface $completion) {
                    $lastMessage = $completion->getLastMessage();

                    return $lastMessage !== null
                        && (string)$lastMessage->getContent() === 'Invoice data here';
                })
            )
            ->willReturn($response);

        $tool = new LlmTool(
            name: 'extract',
            description: 'Extract',
            parameters: ['type' => 'object'],
            llm: $llm,
            promptBuilder: fn(string $content) => new Completion([
                new Message(new TextContent($content), RoleEnum::USER),
            ]),
        );

        $tool->execute(['content' => 'Invoice data here']);
    }

    public function testGetLlmReturnsProvider(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $tool = new LlmTool(
            name: 'test',
            description: 'Test',
            parameters: [],
            llm: $llm,
            promptBuilder: fn(array $args) => new Completion([]),
        );

        $this->assertSame($llm, $tool->getLlm());
    }
}
