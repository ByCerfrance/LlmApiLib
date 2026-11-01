<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Content\TextContent;
use Closure;
use InvalidArgumentException;
use Override;

readonly class PromptNormalizer implements PromptNormalizerInterface
{
    private MessageNormalizer $messageNormalizer;

    /**
     * @param list<PromptTarget> $targets Message categories explicitly selected for normalization.
     * @param (Closure(MessageInterface, TextContent): TextFormat)|null $textFormatResolver Optional per-content format declaration.
     */
    public function __construct(
        private array $targets = [],
        ?TextNormalizerInterface $textNormalizer = null,
        ?ImageNormalizerInterface $imageNormalizer = null,
        TextFormat $textFormat = TextFormat::UNKNOWN,
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
    public function normalize(ChatRequestInterface $request): ChatRequestInterface
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
}
