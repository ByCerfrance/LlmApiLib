<?php

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Provider\Google;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\SkippedWithMessageException;

#[CoversClass(Google::class)]
class GoogleTest extends ProviderTestCase
{
    protected function setUp(): void
    {
        $this->sleep = 2;
        $this->provider = new Google(
            apiKey: getenv('GOOGLE_APIKEY') ?: throw new SkippedWithMessageException(),
            model: new ModelInfo(
                'gemini-flash-latest',
                capabilities: [
                    Capability::TEXT,
                    Capability::JSON_OUTPUT,
                    Capability::JSON_SCHEMA,
                    Capability::TOOLS,
                ],
                inputCost: 10,
                outputCost: 20,
            ),
            client: new CurlAdapter(),
        );
    }
}
