<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAi;
use ByCerfrance\LlmApiLib\Tool\Tool;
use ByCerfrance\LlmApiLib\Tool\ToolCollection;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$client = new class implements ClientInterface {
    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $last = $body['messages'][array_key_last($body['messages'])];
        $finished = $last['role'] === 'tool';
        $message = $finished
            ? ['role' => 'assistant', 'content' => 'Double: ' . $last['content']]
            : ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
                'id' => 'demo-call', 'type' => 'function',
                'function' => ['name' => 'double', 'arguments' => '{"value":21}'],
            ]]];

        return new Response(json_encode([
            'choices' => [['message' => $message, 'finish_reason' => $finished ? 'stop' : 'tool_calls']],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ], JSON_THROW_ON_ERROR));
    }
};
$tools = new ToolCollection(new Tool(
    name: 'double',
    description: 'Double an integer.',
    parameters: ['type' => 'object', 'properties' => ['value' => ['type' => 'integer']], 'required' => ['value'], 'additionalProperties' => false],
    callback: static fn(array $arguments): string => (string) ($arguments['value'] * 2),
));
$chat = new Chat(new OpenAi('unused-demo-key', $client), new Model('demo-tools', capabilities: [Capability::TEXT, Capability::TOOLS]));
$response = $chat->chat(new ChatRequest(new Conversation(['Double 21.']), tools: $tools));
echo (string) $response->getResult()->getMessage()->getContent() . PHP_EOL;
echo 'Provider turns: ' . count($response->getTurns()) . PHP_EOL;
echo 'Tokens across turns: ' . $response->getUsage()->getTotalTokens() . PHP_EOL;
echo 'History messages: ' . count($response->toConversation()) . PHP_EOL;
