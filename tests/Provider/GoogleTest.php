<?php

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Provider\Google;
use ByCerfrance\LlmApiLib\Provider\Mistral;
use PHPUnit\Framework\SkippedWithMessageException;

class GoogleTest extends ProviderTestCase
{
    protected function setUp(): void
    {
        $this->sleep = 2;
        $this->provider = new Google(
            apiKey: getenv('GOOGLE_APIKEY') ?: throw new SkippedWithMessageException(),
            model: 'gemini-flash-latest',
            client: new CurlAdapter(),
        );
    }
}
