<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider\Ovh;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use Override;
use Psr\Http\Message\UriInterface;

readonly class Ovh extends AbstractOpenAiCompatible
{
    #[Override]
    protected function createUri(): UriInterface
    {
        return Uri::createFromString('https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/chat/completions');
    }
}
