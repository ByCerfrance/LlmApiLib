<?php

namespace ByCerfrance\LlmApiLib\Tests\Model;

use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\CostTier;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Model\QualityTier;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Usage\Usage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModelInfo::class)]
#[UsesClass(Capability::class)]
#[UsesClass(CostTier::class)]
#[UsesClass(QualityTier::class)]
#[UsesClass(SelectionStrategy::class)]
#[UsesClass(Usage::class)]
class ModelInfoTest extends TestCase
{
    public function testComputeCost()
    {
        $modelInfo = new ModelInfo(
            name: 'foo',
            inputCost: 10,
            outputCost: 20,
        );

        $this->assertSame(
            0.2,
            $modelInfo->computeCost(new Usage(10000, 5000, 15000))
        );
    }

    public function testComputeCostWithNoPrice()
    {
        $modelInfo = new ModelInfo(name: 'foo');

        $this->assertSame(
            .0,
            $modelInfo->computeCost(new Usage(1000, 500, 1500))
        );
    }

    public function testSupports()
    {
        $modelInfo = new ModelInfo(
            name: 'foo',
            capabilities: [Capability::DOCUMENT, Capability::OCR],
        );

        $this->assertTrue($modelInfo->supports(Capability::DOCUMENT, Capability::OCR));
        $this->assertFalse($modelInfo->supports(Capability::TEXT));
    }

    public function testSupportsWithDefaults()
    {
        $modelInfo = new ModelInfo(
            name: 'foo',
        );

        $this->assertTrue($modelInfo->supports(Capability::TEXT));
        $this->assertTrue($modelInfo->supports(Capability::TEXT, Capability::JSON_OUTPUT));
        $this->assertFalse($modelInfo->supports(Capability::DOCUMENT));
        $this->assertFalse($modelInfo->supports(Capability::TEXT, Capability::JSON_SCHEMA));
    }

    public static function providerBaseScore()
    {
        return [
            // Best quality strategies
            [
                'qualityTier' => QualityTier::PREMIUM,
                'costTier' => CostTier::HIGH,
                'selectionStrategy' => SelectionStrategy::BEST_QUALITY,
                'expected' => 2.9,
            ],
            [
                'qualityTier' => QualityTier::GOOD,
                'costTier' => CostTier::MEDIUM,
                'selectionStrategy' => SelectionStrategy::BEST_QUALITY,
                'expected' => 2.4,
            ],
            [
                'qualityTier' => QualityTier::BASIC,
                'costTier' => CostTier::LOW,
                'selectionStrategy' => SelectionStrategy::BEST_QUALITY,
                'expected' => 1,
            ],
            // Balanced strategies
            [
                'qualityTier' => QualityTier::PREMIUM,
                'costTier' => CostTier::HIGH,
                'selectionStrategy' => SelectionStrategy::BALANCED,
                'expected' => 2,
            ],
            [
                'qualityTier' => QualityTier::GOOD,
                'costTier' => CostTier::MEDIUM,
                'selectionStrategy' => SelectionStrategy::BALANCED,
                'expected' => 2.25,
            ],
            [
                'qualityTier' => QualityTier::BASIC,
                'costTier' => CostTier::LOW,
                'selectionStrategy' => SelectionStrategy::BALANCED,
                'expected' => 1.75,
            ],
            // Cheap strategies
            [
                'qualityTier' => QualityTier::PREMIUM,
                'costTier' => CostTier::HIGH,
                'selectionStrategy' => SelectionStrategy::CHEAP,
                'expected' => 1.1,
            ],
            [
                'qualityTier' => QualityTier::GOOD,
                'costTier' => CostTier::MEDIUM,
                'selectionStrategy' => SelectionStrategy::CHEAP,
                'expected' => 2.1,
            ],
            [
                'qualityTier' => QualityTier::BASIC,
                'costTier' => CostTier::LOW,
                'selectionStrategy' => SelectionStrategy::CHEAP,
                'expected' => 2.5,
            ],
            // Mix strategies
            [
                'qualityTier' => QualityTier::PREMIUM,
                'costTier' => CostTier::LOW,
                'selectionStrategy' => SelectionStrategy::CHEAP,
                'expected' => 3.1,
            ],
            [
                'qualityTier' => QualityTier::BASIC,
                'costTier' => CostTier::HIGH,
                'selectionStrategy' => SelectionStrategy::CHEAP,
                'expected' => 0.5,
            ],
        ];
    }

    #[DataProvider('providerBaseScore')]
    public function testBaseScore(
        QualityTier $qualityTier,
        CostTier $costTier,
        SelectionStrategy $selectionStrategy,
        float $expected,
    ) {
        $modelInfo = new ModelInfo(
            name: 'foo',
            qualityTier: $qualityTier,
            costTier: $costTier,
        );

        $this->assertEquals($expected, round($modelInfo->baseScore($selectionStrategy), 2));
    }

    public function testBaseScoreWithDefaults()
    {
        $modelInfo = new ModelInfo(
            name: 'foo',
        );

        $this->assertEquals(2.1, round($modelInfo->baseScore(SelectionStrategy::CHEAP), 2));
        $this->assertEquals(2.25, round($modelInfo->baseScore(SelectionStrategy::BALANCED), 2));
        $this->assertEquals(2.4, round($modelInfo->baseScore(SelectionStrategy::BEST_QUALITY), 2));
    }
}
