<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Content\TextFormat;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Image\ImageRequestInterface;
use Closure;
use InvalidArgumentException;
use Override;

readonly class PromptNormalizer implements PromptNormalizerInterface
{
    private MessageNormalizer $messageNormalizer;

    /**
     * @param list<PromptTarget> $targets Chat categories or image-request fields selected for normalization.
     * @param (Closure(MessageInterface, TextContent): TextFormat)|null $textFormatResolver Optional per-content chat format declaration; image prompts use $textFormat.
     */
    public function __construct(
        private array $targets = [],
        private ?TextNormalizerInterface $textNormalizer = null,
        private ?ImageNormalizerInterface $imageNormalizer = null,
        private TextFormat $textFormat = TextFormat::UNKNOWN,
        ?Closure $textFormatResolver = null,
    ) {
        if (!array_is_list($targets)) {
            throw new InvalidArgumentException('Prompt targets must be a list.');
        }
        foreach ($targets as $target) {
            if (!$target instanceof PromptTarget) {
                throw new InvalidArgumentException('Expected PromptTarget values.');
            }
        }
        $this->messageNormalizer = new MessageNormalizer($textNormalizer, $imageNormalizer, $textFormat, $textFormatResolver);
    }

    #[Override]
    public function normalize(ChatRequestInterface|ImageRequestInterface $request): ChatRequestInterface|ImageRequestInterface
    {
        return $request instanceof ChatRequestInterface ? $this->normalizeChat($request) : $this->normalizeImageRequest($request);
    }

    private function normalizeChat(ChatRequestInterface $request): ChatRequestInterface
    {
        $messages = [];
        $changed = false;
        foreach ($request->getConversation() as $message) {
            $target = match ($message->getRole()) {
                RoleEnum::SYSTEM => PromptTarget::SYSTEM,
                RoleEnum::USER => PromptTarget::USER,
                RoleEnum::ASSISTANT => PromptTarget::ASSISTANT_HISTORY,
                RoleEnum::TOOL => PromptTarget::TOOL_RESULT,
            };
            $normalized = in_array($target, $this->targets, true) ? $this->messageNormalizer->normalize($message) : $message;
            $messages[] = $normalized;
            $changed = $changed || $normalized !== $message;
        }

        return $changed ? $request->withConversation(new Conversation($messages)) : $request;
    }

    private function normalizeImageRequest(ImageRequestInterface $request): ImageRequestInterface
    {
        $prompt = $request->getPrompt();
        if ($this->textNormalizer !== null && in_array(PromptTarget::IMAGE_PROMPT, $this->targets, true)) {
            $prompt = $this->textNormalizer->normalize($prompt, $this->textFormat);
        }
        $images = $request->getImages();
        if ($this->imageNormalizer !== null && in_array(PromptTarget::IMAGE_REFERENCE, $this->targets, true)) {
            foreach ($images as $index => $image) {
                $images[$index] = $this->imageNormalizer->normalize($image);
            }
        }
        if ($prompt === $request->getPrompt() && $images === $request->getImages()) {
            return $request;
        }

        return new ImageRequest($prompt, $request->getCount(), $request->getOptions(), $request->getAspectRatio(), $images);
    }
}
