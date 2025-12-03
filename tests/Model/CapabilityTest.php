<?php

namespace ByCerfrance\LlmApiLib\Tests\Model;

use ByCerfrance\LlmApiLib\Model\Capability;
use PHPUnit\Framework\TestCase;
use ValueError;

class CapabilityTest extends TestCase
{
    public function testMultipleFromString()
    {
        $capabilities = Capability::multipleFromString(' text ocr  document image ');

        $this->assertEquals(
            $expected = [
                Capability::TEXT,
                Capability::OCR,
                Capability::DOCUMENT,
                Capability::IMAGE,
            ],
            $capabilities,
        );

        $capabilities = Capability::multipleFromString('text, ocr, document, image,', ',');

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
