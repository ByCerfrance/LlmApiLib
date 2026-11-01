<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Content;

use ByCerfrance\LlmApiLib\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Content\RawJsonContent;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Content\TextFormat;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RawJsonContent::class)]
#[UsesClass(ArrayContent::class)]
#[UsesClass(TextContent::class)]
#[UsesClass(TextFormat::class)]
#[UsesClass(Capability::class)]
#[UsesClass(BuildContext::class)]
#[UsesClass(PayloadBuilder::class)]
class RawJsonContentTest extends TestCase
{
    public function testJsonRepresentationIsPreservedWithoutDoubleEncoding(): void
    {
        $json = ' { "amount": 1.2300, "big": 123456789012345678901234567890, "label": "A  B", "escape": "\\u00e9" } ';
        $content = new RawJsonContent($json);
        self::assertSame($json, $content->getContent());
        self::assertSame($json, (string) $content);
        self::assertSame($json, $content->jsonSerialize());
        self::assertSame(['type' => 'text', 'text' => $json], $content->jsonSerialize(true));
        self::assertSame([Capability::TEXT], $content->requiredCapabilities());
        $payload = (new PayloadBuilder())->build(new ArrayContent(
            new TextContent('## Instructions', format: TextFormat::MARKDOWN), $content,
        ));
        self::assertSame([
            ['type' => 'text', 'text' => '## Instructions'],
            ['type' => 'text', 'text' => $json],
        ], $payload);
    }

    /** @return iterable<string, array{string}> */
    public static function validScalars(): iterable
    {
        yield 'number' => [' 1.2300 '];
        yield 'string' => [' "A  B" '];
        yield 'null' => ['null'];
        yield 'boolean' => ['true'];
    }

    #[DataProvider('validScalars')]
    public function testValidScalarDocumentsAreAccepted(string $json): void
    {
        self::assertSame($json, (new RawJsonContent($json))->getContent());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDocuments(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => [" \n"];
        yield 'trailing comma' => ['{"x":1,}'];
        yield 'unquoted text' => ['hello'];
        yield 'multiple documents' => ['{} {}'];
        yield 'invalid utf8' => ["{\"x\":\"\xff\"}"];
    }

    #[DataProvider('invalidDocuments')]
    public function testInvalidJsonIsRejected(string $json): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RawJsonContent($json);
    }
}
