<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider\Mistral;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use Override;
use Psr\Http\Message\UriInterface;

readonly class Mistral extends AbstractOpenAiCompatible
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
