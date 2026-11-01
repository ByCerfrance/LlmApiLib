<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Chat\ChatResponseInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Content\TextContent;
use Closure;
use Override;

readonly class ResponseNormalizer implements ResponseNormalizerInterface
{
    private MessageNormalizer $messageNormalizer;

    /** @param (Closure(MessageInterface, TextContent): TextFormat)|null $textFormatResolver Optional per-content format declaration. */
    public function __construct(
        ?TextNormalizerInterface $textNormalizer = null,
        ?ImageNormalizerInterface $imageNormalizer = null,
        TextFormat $textFormat = TextFormat::UNKNOWN,
        ?Closure $textFormatResolver = null,
    ) {
        $this->messageNormalizer = new MessageNormalizer($textNormalizer, $imageNormalizer, $textFormat, $textFormatResolver);
    }

    #[Override]
    public function normalize(ChatResponseInterface $response): ChatResponseInterface
    {
        $result = $response->getResult();
        $message = $this->messageNormalizer->normalize($result->getMessage(), preserveNative: false);
        if ($message === $result->getMessage()) {
            return $response;
        }

        return new NormalizedChatResponse($response, new NormalizedChatResult($result, $message));
    }
}
