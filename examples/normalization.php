<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Content\RawJsonContent;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Content\TextFormat;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Normalization\NormalizedChatResponse;
use ByCerfrance\LlmApiLib\Normalization\PromptNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptTarget;
use ByCerfrance\LlmApiLib\Normalization\ResponseNormalizer;
use ByCerfrance\LlmApiLib\Normalization\TextNormalizer;
use ByCerfrance\LlmApiLib\Provider\ChatProviderInterface;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequestInterface;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use Override;

require dirname(__DIR__) . '/vendor/autoload.php';

$provider = new readonly class implements ChatProviderInterface {
    #[Override]
    public function getId(): string
    {
        return 'offline';
    }

    #[Override]
    public function complete(ProviderChatRequestInterface $request): ChatResultInterface
    {
        foreach ($request->getRequest()->getConversation() as $message) {
            $content = $message->getContent();
            foreach ($content instanceof ArrayContent ? $content : [$content] as $part) {
                echo 'Sent: ' . $part . PHP_EOL;
            }
        }

        return new ChatResult(new AssistantMessage(' { "answer": "Keep  these spaces" } '), new TokenUsage(), FinishReason::STOP, $this->getId());
    }
};
$textNormalizer = new TextNormalizer(minifyJson: true);
$chat = new Chat(
    $provider,
    new Model('offline-model'),
    promptNormalizer: new PromptNormalizer([PromptTarget::USER], $textNormalizer),
    responseNormalizer: new ResponseNormalizer($textNormalizer),
);
$response = $chat->chat(new ChatRequest(new Conversation([
    new UserMessage(new ArrayContent(
        new TextContent('## Explain these data', format: TextFormat::MARKDOWN),
        new RawJsonContent(' { "question": "Explain crop rotation", "amount": 1.2300 } '),
    )),
])));
echo 'Displayed: ' . $response->getText() . PHP_EOL;
if ($response instanceof NormalizedChatResponse) {
    echo 'Original answer: ' . $response->getOriginalResponse()->getText() . PHP_EOL;
}
foreach ($response->toConversation() as $message) {
    $content = $message->getContent();
    foreach ($content instanceof ArrayContent ? $content : [$content] as $part) {
        echo 'History: ' . $part . PHP_EOL;
    }
}
