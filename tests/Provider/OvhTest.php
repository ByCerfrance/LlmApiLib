<?php

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Provider\Ovh;
use PHPUnit\Framework\SkippedWithMessageException;

class OvhTest extends ProviderTestCase
{
    protected function setUp(): void
    {
        $this->provider = new Ovh(
            apiKey: getenv('OVH_APIKEY') ?: throw new SkippedWithMessageException(),
            model: new ModelInfo('Mistral-7B-Instruct-v0.3', inputCost: 10, outputCost: 20),
            client: new CurlAdapter(),
        );
    }
}
