<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Request;
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponse;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Completion\Content\ContentFactory;
use ByCerfrance\LlmApiLib\Completion\Message\Choices;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\LlmInterface;
use ByCerfrance\LlmApiLib\Usage\Usage;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use SensitiveParameter;

abstract readonly class AbstractProvider implements LlmInterface
{
    protected Usage $usage;

    public function __construct(
        #[SensitiveParameter]
        protected string $apiKey,
        protected string $model,
        protected ClientInterface $client,
    ) {
        $this->usage = new Usage();
    }

    #[Override]
    public function chat(CompletionInterface|MessageInterface|string $completion): CompletionResponseInterface
    {
        if (is_string($completion)) {
            $completion = new Message($completion);
        }
        if ($completion instanceof MessageInterface) {
            $completion = new Completion(messages: [$completion]);
        }

        $request = $this->createRequest($completion);
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

        $usage = new Usage(
            promptTokens: $json['usage']['prompt_tokens'] ?? 0,
            completionTokens: $json['usage']['completion_tokens'] ?? 0,
            totalTokens: $json['usage']['total_tokens'] ?? 0,
        );
        $this->usage->addUsage($usage);

        return new CompletionResponse(
            completion: $completion->withNewMessage($choices),
            usage: $usage,
        );
    }

    protected function createRequest(CompletionInterface $completion): RequestInterface
    {
        return new Request(
            method: Request::HTTP_METHOD_POST,
            uri: $this->createUri($completion),
            body: json_encode($this->createBody($completion)),
            headers: [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ]
        );
    }

    abstract protected function createUri(CompletionInterface $completion): UriInterface;

    protected function createBody(CompletionInterface $completion): array
    {
        if (null === $completion->getModel()) {
            $completion = $completion->withModel($this->model);
        }

        return $completion->jsonSerialize();
    }

    #[Override]
    public function getUsage(): UsageInterface
    {
        return $this->usage;
    }
}
