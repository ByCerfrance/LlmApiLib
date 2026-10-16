<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider\Fixture;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Provider\AbstractProvider;
use Override;
use Psr\Http\Message\UriInterface;

/** @internal Preserves AbstractProvider payload regression coverage during migration. */
readonly class OpenAi extends AbstractProvider
{
    #[Override]
    protected function createUri(CompletionInterface $completion): UriInterface
    {
        return Uri::createFromString('https://api.openai.com/v1/chat/completions');
    }
}
