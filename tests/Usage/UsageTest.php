<?php

namespace ByCerfrance\LlmApiLib\Tests\Usage;

use ByCerfrance\LlmApiLib\Usage\Usage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Usage::class)]
class UsageTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $usage = new Usage(
            promptTokens: 1000,
            completionTokens: 500,
            totalTokens: 1500,
            cachedTokens: 800,
        );

        $this->assertEquals(
            [
                'prompt_tokens' => 1000,
                'completion_tokens' => 500,
                'total_tokens' => 1500,
                'cached_tokens' => 800,
            ],
            $usage->jsonSerialize()
        );
    }

    public function testAddUsage(): void
    {
        $usage = new Usage(
            promptTokens: 1000,
            completionTokens: 500,
            totalTokens: 1500,
            cachedTokens: 400,
        );
        $usage->addUsage(
            new Usage(
                promptTokens: 500,
                completionTokens: 250,
                totalTokens: 750,
                cachedTokens: 300,
            )
        );

        $this->assertEquals(1500, $usage->getPromptTokens());
        $this->assertEquals(750, $usage->getCompletionTokens());
        $this->assertEquals(2250, $usage->getTotalTokens());
        $this->assertEquals(700, $usage->getCachedTokens());
    }

    public function testAddTokens(): void
    {
        $usage = new Usage(
            promptTokens: 1000,
            completionTokens: 500,
            totalTokens: 1500,
            cachedTokens: 400,
        );
        $usage->addTokens(
            promptTokens: 500,
            completionTokens: 250,
            totalTokens: 750,
            cachedTokens: 200,
        );

        $this->assertEquals(1500, $usage->getPromptTokens());
        $this->assertEquals(750, $usage->getCompletionTokens());
        $this->assertEquals(2250, $usage->getTotalTokens());
        $this->assertEquals(600, $usage->getCachedTokens());
    }

    public function testGetPromptTokens(): void
    {
        $usage = new Usage(
            promptTokens: 1000,
            completionTokens: 500,
            totalTokens: 1500,
        );

        $this->assertEquals(1000, $usage->getPromptTokens());
    }

    public function testGetCompletionTokens(): void
    {
        $usage = new Usage(
            promptTokens: 1000,
            completionTokens: 500,
            totalTokens: 1500,
        );

        $this->assertEquals(500, $usage->getCompletionTokens());
    }

    public function testGetTotalTokens(): void
    {
        $usage = new Usage(
            promptTokens: 1000,
            completionTokens: 500,
            totalTokens: 1500,
        );

        $this->assertEquals(1500, $usage->getTotalTokens());
    }

    public function testGetCachedTokens(): void
    {
        $usage = new Usage(
            promptTokens: 1000,
            completionTokens: 500,
            totalTokens: 1500,
            cachedTokens: 800,
        );

        $this->assertEquals(800, $usage->getCachedTokens());
    }

    public function testGetCachedTokensDefaultsToZero(): void
    {
        $usage = new Usage(
            promptTokens: 1000,
            completionTokens: 500,
            totalTokens: 1500,
        );

        $this->assertEquals(0, $usage->getCachedTokens());
    }
}
