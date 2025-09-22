<?php

namespace ByCerfrance\LlmApiLib\Tests\Completion\Content;

use ByCerfrance\LlmApiLib\Completion\Content\JsonContent;
use JsonSerializable;
use Override;
use PHPUnit\Framework\TestCase;

class JsonContentTest extends TestCase
{
    public function testGetContent()
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
    }

    public function testJsonSerialize()
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
}
