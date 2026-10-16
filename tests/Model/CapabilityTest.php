<?php

namespace ByCerfrance\LlmApiLib\Tests\Model;

use ByCerfrance\LlmApiLib\Model\Capability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(Capability::class)]
class CapabilityTest extends TestCase
{
    public function testMultipleFromString()
    {
        $capabilities = Capability::multipleFromString(' text vision  document vision ');

        $this->assertEquals(
            $expected = [
                Capability::TEXT,
                Capability::VISION,
                Capability::DOCUMENT,
            ],
            $capabilities,
        );

        $capabilities = Capability::multipleFromString('text, vision, document, vision,', ',');

        $this->assertEquals(
            $expected,
            $capabilities,
        );
    }

    public function testMultipleFromStringWithInvalidCapability()
    {
        $this->expectException(ValueError::class);

        Capability::multipleFromString('text,bar');
    }
}
