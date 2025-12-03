<?php

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Provider\Google;
use PHPUnit\Framework\SkippedWithMessageException;

class GoogleTest extends ProviderTestCase
{
    protected function setUp(): void
    {
        $this->sleep = 2;
        $this->provider = new Google(
            apiKey: getenv('GOOGLE_APIKEY') ?: throw new SkippedWithMessageException(),
            model: new ModelInfo('gemini-flash-latest', inputCost: 10, outputCost: 20),
            client: new CurlAdapter(),
        );
    }
}
