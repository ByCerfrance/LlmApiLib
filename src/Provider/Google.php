<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Payload\Builder\GoogleChatBuilder;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use Override;
use Psr\Http\Message\UriInterface;

/** Google Gemini's OpenAI compatibility endpoint, not the native Gemini API. */
readonly class Google extends OpenAICompatible
{
    #[Override]
    protected function createUri(): UriInterface
    {
        return Uri::createFromString('https://generativelanguage.googleapis.com/v1beta/openai/chat/completions');
    }

    /** @return iterable<BuilderInterface> */
    #[Override]
    protected function getPayloadBuilders(): iterable
    {
        return [...parent::getPayloadBuilders(), new GoogleChatBuilder()];
    }
}
