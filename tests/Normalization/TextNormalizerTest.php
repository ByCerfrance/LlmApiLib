<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Normalization;

use ByCerfrance\LlmApiLib\Content\TextFormat;
use ByCerfrance\LlmApiLib\Normalization\TextNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextNormalizer::class)]
#[UsesClass(TextFormat::class)]
class TextNormalizerTest extends TestCase
{
    public function testDefaultOptionsPreserveEveryFormat(): void
    {
        $normalizer = new TextNormalizer();
        foreach (TextFormat::cases() as $format) {
            foreach ([" \t hello  world \r\n\r\n", ' { "value": 1.00 } ', ''] as $text) {
                self::assertSame($text, $normalizer->normalize($text, $format));
            }
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function jsonDocuments(): iterable
    {
        yield 'numbers' => [
            ' { "big": 123456789012345678901234567890, "decimal": 1.2300, "exp": 1E+09, "zero": -0 } ',
            '{"big":123456789012345678901234567890,"decimal":1.2300,"exp":1E+09,"zero":-0}',
        ];
        yield 'escaped strings' => [
            ' { "text": "a  b \\"quoted\\" \\n \\t", "path": "C:\\\\folder\\\\", "unicode": "\\u00e9 é" } ',
            '{"text":"a  b \\"quoted\\" \\n \\t","path":"C:\\\\folder\\\\","unicode":"\\u00e9 é"}',
        ];
        yield 'nested array' => ["\r\n[ true, null, { \"x\": [ 1, 2 ] } ]\t", '[true,null,{"x":[1,2]}]'];
        yield 'duplicate keys and empty object' => [' { "x": {}, "x": [] } ', '{"x":{},"x":[]}'];
    }

    #[DataProvider('jsonDocuments')]
    public function testMinificationPreservesJsonLexemes(string $input, string $expected): void
    {
        self::assertTrue(json_validate($input));
        $normalizer = new TextNormalizer(minifyJson: true, trimText: true, collapseSpaces: true);
        foreach (TextFormat::cases() as $format) {
            $actual = $normalizer->normalize($input, $format);
            self::assertSame($format === TextFormat::MARKDOWN ? $input : $expected, $actual);
            self::assertTrue(json_validate($actual));
            self::assertSame($actual, $normalizer->normalize($actual, $format));
        }
    }

    /** @return iterable<string, array{string}> */
    public static function protectedTexts(): iterable
    {
        yield 'markdown' => ["Title  \n\n\n    code  block\n"];
        yield 'python' => ["if True:\n    print('a  b')\n"];
        yield 'fenced json' => ["```json\n{ \"x\": 1 }\n```"];
        yield 'embedded json' => ['Please use { "x": 1 }'];
        yield 'invalid json' => [' { "x": 1, } '];
        yield 'scalar number' => [' 123 '];
        yield 'scalar string' => [' "a  b" '];
        yield 'scalar boolean' => [' true '];
        yield 'invalid utf8' => ["{\"x\":\"\xff\"}"];
        yield 'empty' => [''];
    }

    #[DataProvider('protectedTexts')]
    public function testNonPlainTextIsPreserved(string $text): void
    {
        $normalizer = new TextNormalizer(true, true, true, true, 0, true);
        foreach ([TextFormat::UNKNOWN, TextFormat::JSON] as $format) {
            self::assertSame($text, $normalizer->normalize($text, $format));
        }
    }

    public function testPlainTextCleanupAndIdempotence(): void
    {
        $normalizer = new TextNormalizer(
            trimText: true,
            trimLineEnds: true,
            collapseSpaces: true,
            maxConsecutiveBlankLines: 1,
            normalizeLineEndings: true,
        );
        $input = " \t\r\nHello  \t world  \r\n \t\r\n\r\n\rNext\t line \r\n\t";
        $actual = $normalizer->normalize($input, TextFormat::PLAIN_TEXT);
        self::assertSame("Hello world\n\nNext line", $actual);
        self::assertSame($actual, $normalizer->normalize($actual, TextFormat::PLAIN_TEXT));
    }

    public function testOptionsAreIndependent(): void
    {
        $input = "  a\t  b \r\n\t\r\n\r\nc  ";
        self::assertSame("a\t  b \r\n\t\r\n\r\nc", (new TextNormalizer(trimText: true))->normalize($input, TextFormat::PLAIN_TEXT));
        self::assertSame("  a\t  b\r\n\r\n\r\nc", (new TextNormalizer(trimLineEnds: true))->normalize($input, TextFormat::PLAIN_TEXT));
        self::assertSame(" a b \r\n \r\n\r\nc ", (new TextNormalizer(collapseSpaces: true))->normalize($input, TextFormat::PLAIN_TEXT));
        self::assertSame("  a\t  b \n\t\n\nc  ", (new TextNormalizer(normalizeLineEndings: true))->normalize($input, TextFormat::PLAIN_TEXT));
        self::assertSame("  a\t  b \r\nc  ", (new TextNormalizer(maxConsecutiveBlankLines: 0))->normalize($input, TextFormat::PLAIN_TEXT));
    }

    public function testBlankLineLimitPreservesMixedLineEndingsAndFinalNewline(): void
    {
        $normalizer = new TextNormalizer(maxConsecutiveBlankLines: 1);
        $input = "a\r\n \t\r\n\n\rb\r\r\rc\n";
        $expected = "a\r\n \t\r\nb\r\rc\n";
        self::assertSame($expected, $normalizer->normalize($input, TextFormat::PLAIN_TEXT));
        self::assertSame($expected, $normalizer->normalize($expected, TextFormat::PLAIN_TEXT));
    }

    public function testJsonBypassesWhitespaceCleanupWhenMinificationIsDisabled(): void
    {
        $input = " { \"text\": \"a  b\" } \n";
        self::assertSame($input, (new TextNormalizer(trimText: true, collapseSpaces: true))->normalize($input, TextFormat::PLAIN_TEXT));
    }

    public function testNegativeBlankLineLimitIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TextNormalizer(maxConsecutiveBlankLines: -1);
    }

    public function testPlainTextBecomingValidJsonIsNormalizedInOnePass(): void
    {
        $input = " { \"text\": \"a\tb\" } ";
        self::assertFalse(json_validate($input));
        $normalizer = new TextNormalizer(minifyJson: true, collapseSpaces: true);
        $output = $normalizer->normalize($input, TextFormat::PLAIN_TEXT);
        self::assertSame('{"text":"a b"}', $output);
        self::assertSame($output, $normalizer->normalize($output, TextFormat::PLAIN_TEXT));
        self::assertSame($input, $normalizer->normalize($input, TextFormat::JSON));
    }

    public function testTrimmingLineEndsPreservesLongInternalWhitespaceRuns(): void
    {
        $spaces = str_repeat(' ', 100000);
        $input = 'start' . $spaces . 'end' . $spaces . "\r\n";
        self::assertSame('start' . $spaces . "end\r\n", (new TextNormalizer(trimLineEnds: true))->normalize($input, TextFormat::PLAIN_TEXT));
    }

    /** @return iterable<string, array{string, string}> */
    public static function markdownDocuments(): iterable
    {
        yield 'mixed instructions and data' => [
            "## Instructions  \n\nKeep  spacing and `inline  code`.\n```json\n{\n  \"label\": \"A  B\",\n  \"amount\": 1.2300\n}\n```\n\n-   List item\n",
            "## Instructions  \n\nKeep  spacing and `inline  code`.\n```json\n{\"label\":\"A  B\",\"amount\":1.2300}\n```\n\n-   List item\n",
        ];
        yield 'tilde fence and crlf' => [
            "  ~~~~JSON\r\n  [ 1, 2, \"a  b\" ]\r\n  ~~~~~ \t\r\n",
            "  ~~~~JSON\r\n  [1,2,\"a  b\"]\r\n  ~~~~~ \t\r\n",
        ];
        yield 'long outer fence protects embedded example' => [
            "````markdown\n```json\n{ \"x\": 1 }\n```\n````\n",
            "````markdown\n```json\n{ \"x\": 1 }\n```\n````\n",
        ];
        yield 'unknown language then json' => [
            "```python\n    print('a  b')\n```\n```json\n{ \"x\": 1 }\n```",
            "```python\n    print('a  b')\n```\n```json\n{\"x\":1}\n```",
        ];
        yield 'invalid json' => ["```json\n{ \"x\": 1, }\n```", "```json\n{ \"x\": 1, }\n```"];
        yield 'unclosed fence' => ["```json\n{ \"x\": 1 }\n", "```json\n{ \"x\": 1 }\n"];
        yield 'short closing fence' => ["````json\n{ \"x\": 1 }\n```", "````json\n{ \"x\": 1 }\n```"];
        yield 'different closing marker' => ["```json\n{ \"x\": 1 }\n~~~", "```json\n{ \"x\": 1 }\n~~~"];
        yield 'indented code' => ["    ```json\n    { \"x\": 1 }\n    ```", "    ```json\n    { \"x\": 1 }\n    ```"];
        yield 'inline code' => ['Use `{"label": "A  B"}` unchanged.', 'Use `{"label": "A  B"}` unchanged.'];
        yield 'unfenced json' => [' { "x": 1 } ', ' { "x": 1 } '];
        yield 'scalar json' => ["```json\n \"A  B\" \n```", "```json\n \"A  B\" \n```"];
        yield 'multiple fences and mixed endings' => [
            "```json\r{ \"x\": 1 }\r```\n~~~json\n[ 2 ]\n~~~",
            "```json\r{\"x\":1}\r```\n~~~json\n[2]\n~~~",
        ];
        yield 'unclosed block after valid block' => [
            "```json\n[ 1 ]\n```\n```json\n[ 2 ]",
            "```json\n[1]\n```\n```json\n[ 2 ]",
        ];
    }

    #[DataProvider('markdownDocuments')]
    public function testMarkdownOnlyMinifiesClosedJsonBlocks(string $input, string $expected): void
    {
        $normalizer = new TextNormalizer(true, true, true, true, 0, true);
        $actual = $normalizer->normalize($input, TextFormat::MARKDOWN);
        self::assertSame($expected, $actual);
        self::assertSame($actual, $normalizer->normalize($actual, TextFormat::MARKDOWN));
        self::assertSame($input, (new TextNormalizer(trimText: true, collapseSpaces: true))->normalize($input, TextFormat::MARKDOWN));
    }
}
