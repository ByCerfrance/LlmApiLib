<?php

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Provider\Mistral;
use PHPUnit\Framework\SkippedWithMessageException;

class MistralTest extends ProviderTestCase
{
    protected function setUp(): void
    {
        $this->sleep = 2;
        $this->provider = new Mistral(
            apiKey: getenv('MISTRAL_APIKEY') ?: throw new SkippedWithMessageException(),
            model: 'open-mistral-7b',
            client: new CurlAdapter(),
        );
    }
}
