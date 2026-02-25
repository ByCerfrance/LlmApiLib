<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use ByCerfrance\LlmApiLib\Completion\Content\JsonContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Model\Capability;
use JsonSerializable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonContent::class)]
#[UsesClass(Capability::class)]
#[UsesClass(TextContent::class)]
class JsonContentTest extends TestCase
{
    public function testGetContent(): void
    {
        $content = new JsonContent(
            content: new class implements JsonSerializable {
                private string $foo = 'foo';
                private string $bar = 'bar';

                #[Override]
                public function jsonSerialize(): array
                {
                    return [$this->foo, $this->bar];
                }
            }
        );

        $this->assertEquals(
            '["foo","bar"]',
            $content->getContent(),
        );
        $this->assertEquals(
            '["foo","bar"]',
            (string)$content,
        );
    }

    public function testJsonSerialize(): void
    {
        $content = new JsonContent(
            content: new class implements JsonSerializable {
                private string $foo = 'foo';
                private string $bar = 'bar';

                #[Override]
                public function jsonSerialize(): array
                {
                    return [$this->foo, $this->bar];
                }
            }
        );

        $this->assertEquals(
            '["foo","bar"]',
            $content->jsonSerialize(),
        );
        $this->assertEquals(
            [
                'type' => 'text',
                'text' => '["foo","bar"]',
            ],
            $content->jsonSerialize(true),
        );
    }

    public function testRequiredCapabilities(): void
    {
        $content = new JsonContent('');

        $this->assertEquals(
            [Capability::TEXT],
            $content->requiredCapabilities(),
        );
    }
}
