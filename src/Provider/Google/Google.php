<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider\Google;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Provider\OpenAiCompatible\AbstractOpenAiCompatible;
use Override;
use Psr\Http\Message\UriInterface;

/** Google Gemini's OpenAI compatibility endpoint, not the native Gemini API. */
readonly class Google extends AbstractOpenAiCompatible
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
