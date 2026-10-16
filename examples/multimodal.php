<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Chat\ResponseFormat\JsonSchemaFormat;
use ByCerfrance\LlmApiLib\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAi;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

// Offline fixture: no image download and no API call are made.
$client = new class implements ClientInterface {
    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $payload = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

        return new Response(json_encode([
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => '{"description":"Demo field"}'],
                'finish_reason' => 'stop',
            ]],
        ], JSON_THROW_ON_ERROR));
    }
};

$model = new Model(
    name: 'demo-vision-model',
    capabilities: [Capability::TEXT, Capability::VISION, Capability::JSON_SCHEMA],
    maxContextTokens: 32000,
    maxOutputTokens: 1000,
    tunable: false,
);
$chat = new Chat(new OpenAi('unused-demo-key', $client), $model);
$response = $chat->chat(new ChatRequest(
    conversation: new Conversation([new UserMessage(new ArrayContent(
        'Describe this image using the requested JSON format.',
        new ImageUrlContent('https://example.test/field.png'),
    ))]),
    maxTokens: 200,
    responseFormat: new JsonSchemaFormat('description', [
        'type' => 'object',
        'properties' => ['description' => ['type' => 'string']],
        'required' => ['description'],
        'additionalProperties' => false,
    ], strict: true),
));

echo $response->getText() . PHP_EOL;
