<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Payload\Builder\CompletionBuilder;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use Override;
use Psr\Http\Message\UriInterface;

readonly class Mistral extends AbstractProvider
{
    #[Override]
    protected function createUri(CompletionInterface $completion): UriInterface
    {
        return Uri::createFromString('https://api.mistral.ai/v1/chat/completions');
    }

    /**
     * @return iterable<BuilderInterface>
     */
    #[Override]
    protected function getPayloadBuilders(): iterable
    {
        return [
            new CompletionBuilder(maxCompletionTokens: false),
        ];
    }
}
