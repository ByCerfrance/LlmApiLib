<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\OutputModality;
use ByCerfrance\LlmApiLib\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\GoogleNative;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

require dirname(__DIR__) . '/vendor/autoload.php';

$live = in_array('--live', $argv, true);
$key = $live ? (getenv('GEMINI_API_KEY') ?: getenv('GOOGLE_APIKEY') ?: throw new RuntimeException('Set GEMINI_API_KEY for live generation.')) : 'unused-demo-key';
$client = $live ? new CurlAdapter() : new class implements ClientInterface {
    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        echo 'Sent native history: ' . count($body['contents']) . ' messages' . PHP_EOL;
        return new Response(json_encode(['candidates' => [['content' => ['role' => 'model', 'parts' => [
            ['text' => 'Offline image fixture'],
            ['inlineData' => ['mimeType' => 'image/png', 'data' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg=='], 'thoughtSignature' => 'offline-opaque-signature'],
        ]], 'finishReason' => 'STOP']]], JSON_THROW_ON_ERROR));
    }
};
$chat = new Chat(new GoogleNative($key, $client), new Model('gemini-3.1-flash-image'));
$request = new ChatRequest(new Conversation(['Generate a simple illustration of a farm with a barn.']),
    outputModalities: [OutputModality::TEXT, OutputModality::IMAGE], imageConfig: ['aspectRatio' => '1:1', 'imageSize' => '1K']);
$first = $chat->chat($request);
$edited = $chat->chat($request->withConversation($first->toConversation()->withNewMessage('Make the barn red.')));
$branchA = $chat->chat($request->withConversation($edited->toConversation()->withNewMessage('Add a tree beside the barn.')));
$branchB = $chat->chat($request->withConversation($edited->toConversation()->withNewMessage('Add a small lake instead.')));
echo sprintf('Histories: first=%d edited=%d branchA=%d branchB=%d', count($first->toConversation()), count($edited->toConversation()), count($branchA->toConversation()), count($branchB->toConversation())) . PHP_EOL;
echo $live ? 'Live calls completed. Inspect ImageContent assets on response messages.' . PHP_EOL : 'Offline only: no API calls made.' . PHP_EOL;
