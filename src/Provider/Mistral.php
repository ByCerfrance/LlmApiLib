<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Request;
use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Completions\Completions;
use ByCerfrance\LlmApiLib\Completions\CompletionsInterface;
use ByCerfrance\LlmApiLib\Completions\Content\ContentFactory;
use ByCerfrance\LlmApiLib\Completions\Message\Choices;
use ByCerfrance\LlmApiLib\Completions\Message\Message;
use ByCerfrance\LlmApiLib\Completions\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Completions\Message\RoleEnum;
use ByCerfrance\LlmApiLib\LlmInterface;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use SensitiveParameter;

readonly class Mistral implements LlmInterface
{
    public function __construct(
        #[SensitiveParameter]
        protected string $apiKey,
        protected string $model,
        protected ClientInterface $client,
        protected int $maxTokens = 1000,
        protected int|float $temperature = 1,
        protected int|float $top_p = 1,
    ) {
    }

    protected function createUri(): UriInterface
    {
        return Uri::createFromString('https://api.mistral.ai/v1/chat/completions');
    }

    protected function createRequest(CompletionsInterface $completions): RequestInterface
    {
        $request = new Request(
            method: Request::HTTP_METHOD_POST,
            uri: $this->createUri(),
            body: $body = json_encode([
                "max_tokens" => $this->maxTokens,
                "messages" => $completions,
                "model" => $this->model,
                "stream" => false,
                "temperature" => $this->temperature,
                "top_p" => $this->top_p,
            ]),
            headers: [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ]
        );

        return $request;
    }

    #[Override]
    public function chat(CompletionsInterface|MessageInterface|string $completions): CompletionsInterface
    {
        if (is_string($completions)) {
            $completions = new Completions(new Message($completions));
        }
        if ($completions instanceof MessageInterface) {
            $completions = new Completions($completions);
        }

        $request = $this->createRequest($completions);
        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(
                sprintf(
                    'Invalid response (%d %s)',
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                )
            );
        }

        $json = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);

        $choices = new Choices(
            ...array_map(
                fn(array $choice) => new Message(
                    ContentFactory::create($choice['message']['content']),
                    role: RoleEnum::from($choice['message']['role'])
                ),
                $json['choices']
            )
        );

        return $completions->withNewMessage($choices);
    }
}
