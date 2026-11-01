<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\Message;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\ProviderAssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\SystemMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Content\ContentInterface;
use ByCerfrance\LlmApiLib\Content\ImageContent;
use ByCerfrance\LlmApiLib\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Content\RawJsonContent;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Content\TextFormat;
use ByCerfrance\LlmApiLib\Tool\ToolResult;
use Closure;

/** @internal Shared content traversal; unknown message/content types retain their metadata. */
readonly class MessageNormalizer
{
    /** @param (Closure(MessageInterface, TextContent): TextFormat)|null $textFormatResolver */
    public function __construct(
        private ?TextNormalizerInterface $textNormalizer = null,
        private ?ImageNormalizerInterface $imageNormalizer = null,
        private TextFormat $textFormat = TextFormat::UNKNOWN,
        private ?Closure $textFormatResolver = null,
    ) {
    }

    public function normalize(MessageInterface $message, bool $preserveNative = true): MessageInterface
    {
        if (!in_array($message::class, [Message::class, SystemMessage::class, UserMessage::class, AssistantMessage::class, ProviderAssistantMessage::class, ToolResult::class], true)
            || ($preserveNative && $message instanceof ProviderAssistantMessage)) {
            return $message;
        }
        $content = $this->normalizeContent($message->getContent(), $message);
        if ($content === $message->getContent()) {
            return $message;
        }

        return match ($message::class) {
            ProviderAssistantMessage::class => new ProviderAssistantMessage($content, $message->getToolCalls(), $message->protocol, $message->providerId, $message->modelName, $message->getNativeContent()),
            AssistantMessage::class => new AssistantMessage($content, $message->getToolCalls()),
            SystemMessage::class => new SystemMessage($content),
            UserMessage::class => new UserMessage($content),
            ToolResult::class => new ToolResult($message->getToolCallId(), $content),
            default => new Message($content, $message->getRole()),
        };
    }

    private function normalizeContent(ContentInterface $content, MessageInterface $message): ContentInterface
    {
        if ($content::class === RawJsonContent::class && $this->textNormalizer !== null) {
            $original = $content->getContent();
            $text = $this->textNormalizer->normalize($original, TextFormat::JSON);

            return $text === $original ? $content : new RawJsonContent($text);
        }
        if ($content::class === TextContent::class && $this->textNormalizer !== null) {
            $format = $this->textFormatResolver !== null ? ($this->textFormatResolver)($message, $content) : ($content->getFormat() ?? $this->textFormat);
            $original = $content->getContent();
            $text = $this->textNormalizer->normalize($original, $format);

            return $text === $original ? $content : new TextContent($text, format: $content->getFormat());
        }
        if (($content::class === ImageContent::class || $content::class === ImageUrlContent::class) && $this->imageNormalizer !== null) {
            return $this->imageNormalizer->normalize($content);
        }
        if ($content::class === ArrayContent::class) {
            $parts = [];
            $changed = false;
            foreach ($content as $part) {
                $normalized = $this->normalizeContent($part, $message);
                $parts[] = $normalized;
                $changed = $changed || $normalized !== $part;
            }

            return $changed ? new ArrayContent(...$parts) : $content;
        }

        return $content;
    }
}
