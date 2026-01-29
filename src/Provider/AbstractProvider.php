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
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Usage\Usage;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SensitiveParameter;

/**
 * @internal Do not use in project code
 */
abstract readonly class AbstractProvider implements LlmInterface
{
    protected ModelInfo $model;
    protected Usage $usage;

    public function __construct(
        #[SensitiveParameter]
        protected string $apiKey,
        ModelInfo|string $model,
        protected ClientInterface $client,
        /** @deprecated Use capabilities of ModelInfo instead */
        ?array $capabilities = null,
    ) {
        if (true === is_string($model)) {
            $capabilities ?: trigger_error('The $capabilities argument is deprecated since v1.5.0', E_USER_DEPRECATED);
            $model = new ModelInfo(
                name: $model,
                capabilities: $capabilities ?? [],
            );
        }
        $this->model = $model;

        $this->usage = new Usage();
    }

    #[Override]
    public function chat(
        CompletionInterface|MessageInterface|string $completion,
        ?LoggerInterface $logger = null,
    ): CompletionResponseInterface {
        if (is_string($completion)) {
            $completion = new Message($completion);
        }
        if ($completion instanceof MessageInterface) {
            $completion = new Completion(messages: [$completion]);
        }

        $request = $this->createRequest($completion);

        $logger?->debug(
            'LLM request initiated on {model}',
            [
                'provider' => static::class,
                'model' => $this->model->name,
                'uri' => (string)$request->getUri(),
                'messages_count' => count($completion),
            ]
        );

        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            $logger?->error(
                'LLM request failed on {model} ({status} {reason})',
                [
                    'provider' => static::class,
                    'model' => $this->model->name,
                    'status' => $response->getStatusCode(),
                    'reason' => $response->getReasonPhrase(),
                    'body_excerpt' => (function (ResponseInterface $response) {
                        $body = $response->getBody();
                        $body->isSeekable() && $body->rewind();

                        return $body->read(500);
                    })(
                        $response
                    ),
                ]
            );

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

        $logger?->info(
            'LLM completion successful on {model} ({total_tokens} tokens, cost: {cost})',
            [
                'provider' => static::class,
                'model' => $this->model->name,
                'prompt_tokens' => $usage->getPromptTokens(),
                'completion_tokens' => $usage->getCompletionTokens(),
                'total_tokens' => $usage->getTotalTokens(),
                'cost' => $this->model->computeCost($usage),
                'choices_count' => count($choices),
                'finish_reason' => $json['choices'][0]['finish_reason'] ?? null,
            ]
        );

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
    public function getScoring(SelectionStrategy $strategy): float
    {
        return $this->model->baseScore($strategy);
    }

    #[Override]
    public function getUsage(): UsageInterface
    {
        return $this->usage;
    }

    #[Override]
    public function getCost(int $precision = 4): float
    {
        return $this->model->computeCost($this->getUsage(), $precision);
    }

    #[Override]
    public function getCapabilities(): array
    {
        return $this->model->capabilities;
    }

    #[Override]
    public function supports(Capability $capability, Capability ...$_capability): bool
    {
        return $this->model->supports($capability, ...$_capability);
    }
}
