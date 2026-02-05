<?php

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Provider\Mistral;
use PHPUnit\Framework\SkippedWithMessageException;

class MistralTest extends ProviderTestCase
{
    protected function setUp(): void
    {
        $this->sleep = 2;
        $this->provider = new Mistral(
            apiKey: getenv('MISTRAL_APIKEY') ?: throw new SkippedWithMessageException(),
            model: new ModelInfo(
                'open-mistral-7b',
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
