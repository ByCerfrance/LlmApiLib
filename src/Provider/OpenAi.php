<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use Psr\Http\Message\UriInterface;

readonly class OpenAi extends AbstractProvider
{
    protected function createUri(CompletionInterface $completion): UriInterface
    {
        return Uri::createFromString('https://api.openai.com/v1/chat/completions');
    }
}
