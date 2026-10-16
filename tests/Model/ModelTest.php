<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Model;

use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\Model;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Model::class)]
#[UsesClass(Capability::class)]
class ModelTest extends TestCase
{
    public function testUnknownSupportIsNotAnEmptyCapabilityList(): void
    {
        $unknown = new Model('custom');
        $known = new Model('text-model', capabilities: [Capability::TEXT]);
        $none = new Model('no-capabilities', capabilities: []);
        $this->assertNull($unknown->supports(Capability::VISION));
        $this->assertNull($unknown->getCapabilities());
        $this->assertTrue($known->supports(Capability::TEXT));
        $this->assertFalse($known->supports(Capability::VISION));
        $this->assertFalse($none->supports(Capability::TEXT));
        $this->assertNull($unknown->isTunable());
        $this->assertSame([], $unknown->getUnsupportedParameters());
    }

    public function testTechnicalLimitsAndConstraintsAreRetained(): void
    {
        $model = new Model('custom', maxContextTokens: 128000, maxOutputTokens: 4000, tunable: false,
            unsupportedParameters: ['service_tier'], parameterValues: ['reasoning_effort' => ['high']]);
        $this->assertSame(128000, $model->getMaxContextTokens());
        $this->assertSame(4000, $model->getMaxOutputTokens());
        $this->assertFalse($model->isTunable());
        $this->assertContains('temperature', $model->getUnsupportedParameters());
        $this->assertContains('top_k', $model->getUnsupportedParameters());
        $this->assertContains('service_tier', $model->getUnsupportedParameters());
        $this->assertSame(['reasoning_effort' => ['high']], $model->getParameterValues());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPaths(): iterable
    {
        yield 'history' => ['messages'];
        yield 'tools' => ['tools.0.parameters'];
        yield 'schema' => ['response_format.json_schema.schema.temperature'];
        yield 'empty segment' => ['response_format..strict'];
    }

    #[DataProvider('invalidPaths')]
    public function testConstraintsCannotTargetContentOrSchema(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Model('custom', unsupportedParameters: [$path]);
    }

    public function testInvalidTokenLimitIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Model('custom', maxOutputTokens: 0);
    }
}
