<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Uri;
use Override;
use Psr\Http\Message\UriInterface;

readonly class OpenAi extends OpenAICompatible
{
    #[Override]
    protected function createUri(): UriInterface
    {
        return Uri::createFromString('https://api.openai.com/v1/chat/completions');
    }
}
