<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Model\ModelInterface;
use ByCerfrance\LlmApiLib\Provider\ChatProviderInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use Override;

readonly class Chat implements ChatInterface
{
    public function __construct(
        private ChatProviderInterface $provider,
        private ModelInterface $model,
        private ?int $maxTokens = null,
        private int|float|null $temperature = null,
        private int|float|null $topP = null,
    ) {
        // Apply the same validation to defaults and explicit request options.
        new ChatRequest(new Conversation(), $maxTokens, $temperature, $topP);
    }

    #[Override]
    public function chat(ChatRequestInterface|string $request): ChatResponseInterface
    {
        if (is_string($request)) {
            $request = new ChatRequest(new Conversation([$request]));
        }

        $resolved = new ChatRequest(
            conversation: $request->getConversation(),
            maxTokens: $request->getMaxTokens() ?? $this->maxTokens,
            temperature: $request->getTemperature() ?? $this->temperature,
            topP: $request->getTopP() ?? $this->topP,
        );
        $result = $this->provider->complete(new ProviderChatRequest($this->model, $resolved));

        return new ChatResponse(
            conversation: $request->getConversation()->withNewMessage($result->getMessage()),
            result: $result,
        );
    }
}
