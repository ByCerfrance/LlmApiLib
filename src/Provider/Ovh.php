<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use Psr\Http\Message\UriInterface;

readonly class Ovh extends AbstractProvider
{
    protected function createUri(CompletionInterface $completion): UriInterface
    {
        return Uri::createFromString('https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/chat/completions');
    }
}
