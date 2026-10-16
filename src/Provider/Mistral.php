<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Payload\Builder\MistralChatBuilder;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use Override;
use Psr\Http\Message\UriInterface;

readonly class Mistral extends OpenAICompatible
{
    #[Override]
    protected function createUri(): UriInterface
    {
        return Uri::createFromString('https://api.mistral.ai/v1/chat/completions');
    }

    /** @return iterable<BuilderInterface> */
    #[Override]
    protected function getPayloadBuilders(): iterable
    {
        return [...parent::getPayloadBuilders(), new MistralChatBuilder()];
    }
}
