<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Observation\UsageObserver;
use ByCerfrance\LlmApiLib\Provider\ObservedProvider;
use ByCerfrance\LlmApiLib\Provider\OpenAi;
use ByCerfrance\LlmApiLib\Usage\Pricing;
use ByCerfrance\LlmApiLib\Usage\UsageCollector;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$client = new class implements ClientInterface {
    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return new Response('{"choices":[{"message":{"role":"assistant","content":"Done"},"finish_reason":"stop"}],"usage":{"prompt_tokens":1000,"completion_tokens":150,"total_tokens":1150,"prompt_tokens_details":{"cached_tokens":200}}}');
    }
};
$collector = new UsageCollector();
$pricing = new Pricing('USD', input: 10, output: 20, cachedInput: 2, perTokens: 1000);
$model = new Model('demo-model', pricing: $pricing);
$chat = new Chat(new ObservedProvider(new OpenAi('unused-demo-key', $client), new UsageObserver($collector)), $model);
$first = $chat->chat('First call');
$snapshot = $collector->snapshot();
$chat->chat('Second call');
echo 'First snapshot tokens: ' . $snapshot->get('total', 'token')->getValue() . PHP_EOL;
echo 'Current tokens: ' . $collector->snapshot()->get('total', 'token')->getValue() . PHP_EOL;
$cost = $pricing->estimate($first->getUsage());
echo 'First call estimated cost: ' . $cost->amount . ' ' . $cost->currency . PHP_EOL;
