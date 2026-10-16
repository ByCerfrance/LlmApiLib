<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Completion\Message\SystemMessage;
use ByCerfrance\LlmApiLib\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\OpenAi;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

// A deterministic local transport: this example never makes a network request.
$client = new class implements ClientInterface {
    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $payload = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $lastMessage = $payload['messages'][array_key_last($payload['messages'])]['content'];
        printf("Request history: %d messages\n", count($payload['messages']));

        return new Response(json_encode([
            'model' => 'demo-model',
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'Demo answer to: ' . $lastMessage],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ], JSON_THROW_ON_ERROR));
    }
};

$chat = new Chat(new OpenAi('unused-demo-key', $client), new Model('demo-model'));
$request = new ChatRequest(
    conversation: new Conversation([
        new SystemMessage('You are an agricultural advisor. Reply in French.'),
        'Explain crop rotation.',
    ]),
    maxTokens: 250,
);

$first = $chat->chat($request);
$second = $chat->chat($request->withConversation(
    $first->toConversation()->withNewMessage('Give an example over three years.'),
));

// Fork from the first response, not from the second exchange.
$branch = $chat->chat($request->withConversation(
    $first->toConversation()->withNewMessage('Summarize in one sentence instead.'),
));

foreach ([$first, $second, $branch] as $response) {
    printf("%s\n", (string) $response->getResult()->getMessage()->getContent());
    printf("Tokens for this call: %d\n", $response->getUsage()->getTotalTokens());
}

printf("Histories: original=%d, first=%d, second=%d, branch=%d\n",
    count($request->getConversation()),
    count($first->toConversation()),
    count($second->toConversation()),
    count($branch->toConversation()),
);
