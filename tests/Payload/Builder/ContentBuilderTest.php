<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Payload\Builder;

use ByCerfrance\LlmApiLib\Completion\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Completion\Content\DocumentUrlContent;
use ByCerfrance\LlmApiLib\Completion\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Completion\Content\InputAudioContent;
use ByCerfrance\LlmApiLib\Completion\Content\JsonContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\Builder\ContentBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Tests\Payload\Support\PayloadReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentBuilder::class)]
class ContentBuilderTest extends TestCase
{
    public function testSupportsContentOnly(): void
    {
        $builder = new ContentBuilder();

        $this->assertTrue($builder->supports(new TextContent('hello'), new BuildContext()));
        $this->assertFalse($builder->supports(new \stdClass(), new BuildContext()));
    }

    public function testBuildTextContent(): void
    {
        $content = new TextContent('hello {name}', ['name' => 'world']);

        $payload = (new ContentBuilder())->build($content, new PayloadBuilder(), new BuildContext());

        $this->assertSame(PayloadReference::content($content), $payload);
    }

    public function testBuildJsonContent(): void
    {
        $content = new JsonContent(['answer' => 42]);

        $payload = (new ContentBuilder())->build($content, new PayloadBuilder(), new BuildContext());

        $this->assertSame(PayloadReference::content($content), $payload);
    }

    public function testBuildArrayContentEncapsulatesChildren(): void
    {
        $content = new ArrayContent(
            new TextContent('hello'),
            new JsonContent(['answer' => 42]),
        );

        $payload = (new ContentBuilder())->build($content, new PayloadBuilder(), new BuildContext());

        $this->assertSame(PayloadReference::content($content), $payload);
    }

    public function testBuildInputAudioContent(): void
    {
        $content = new InputAudioContent('abc123', 'wav');

        $payload = (new ContentBuilder())->build($content, new PayloadBuilder(), new BuildContext());

        $this->assertSame(PayloadReference::content($content), $payload);
    }

    public function testBuildImageUrlContent(): void
    {
        $content = new ImageUrlContent('https://example.com/image.png', 'high');

        $payload = (new ContentBuilder())->build($content, new PayloadBuilder(), new BuildContext());

        $this->assertSame(PayloadReference::content($content), $payload);
    }

    public function testBuildDocumentUrlContent(): void
    {
        $content = new DocumentUrlContent('https://example.com/doc.pdf', 'doc.pdf', 'auto');

        $payload = (new ContentBuilder())->build($content, new PayloadBuilder(), new BuildContext());

        $this->assertSame(PayloadReference::content($content), $payload);
    }
}
