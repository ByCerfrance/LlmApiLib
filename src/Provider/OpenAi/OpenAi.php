<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider\OpenAi;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use Override;
use Psr\Http\Message\UriInterface;

readonly class OpenAi extends AbstractOpenAiCompatible
{
    #[Override]
    protected function createUri(): UriInterface
    {
        return Uri::createFromString('https://api.openai.com/v1/chat/completions');
    }
}
