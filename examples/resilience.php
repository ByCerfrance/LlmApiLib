<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAi;
use ByCerfrance\LlmApiLib\Provider\RetryProvider;
use ByCerfrance\LlmApiLib\Resilience\RetryRunner;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$client = new class implements ClientInterface {
    private int $attempts = 0;

    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        echo 'Attempt: ' . ++$this->attempts . PHP_EOL;
        return $this->attempts === 1
            ? new Response('', 429, headers: ['Retry-After' => '1'])
            : new Response('{"choices":[{"message":{"role":"assistant","content":"Recovered"},"finish_reason":"stop"}]}');
    }
};
$provider = new RetryProvider(new OpenAi('unused-demo-key', $client), runner: new RetryRunner(
    static function (int $milliseconds): void {
        echo 'Simulated wait: ' . $milliseconds . 'ms' . PHP_EOL;
    },
));
$response = (new Chat($provider, new Model('demo-model')))->chat('Hello');
echo (string) $response->getResult()->getMessage()->getContent() . PHP_EOL;
