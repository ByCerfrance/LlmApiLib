<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Request;
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\CompletionResponse;
use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Completion\FinishReason;
use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\Choice;
use ByCerfrance\LlmApiLib\Completion\Message\Choices;
use ByCerfrance\LlmApiLib\Completion\Message\MessageFactory;
use ByCerfrance\LlmApiLib\Completion\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Completion\Message\UserMessage;
use ByCerfrance\LlmApiLib\LlmInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Model\SelectionStrategy;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Usage\Usage;
use ByCerfrance\LlmApiLib\Usage\UsageInterface;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
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
        protected array $extraBody = [],
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
            $completion = new UserMessage($completion);
        }
        if ($completion instanceof MessageInterface) {
            $completion = new Completion(messages: [$completion]);
        }

        if (null !== $completion->getReasoningEffort()
            && !$this->model->supports(Capability::REASONING)
        ) {
            $logger?->warning(
                'Reasoning effort ignored: model {model} does not support reasoning',
                ['model' => $this->model->name, 'reasoning_effort' => $completion->getReasoningEffort()->value],
            );
            $completion = $completion->withReasoningEffort(null);
        }

        $totalUsage = new Usage();
        $iteration = 0;
        $maxIterations = $completion->getMaxToolIterations();
        $tools = $completion->getTools();

        do {
            $iteration++;
            $request = $this->createRequest($completion);

            $logger?->debug(
                'LLM request initiated on {model}',
                [
                    'provider' => static::class,
                    'model' => $this->model->name,
                    'uri' => (string)$request->getUri(),
                    'messages_count' => count($completion),
                    'tool_iteration' => $iteration,
                ]
            );

            $response = $this->client->sendRequest($request);

            if ($response->getStatusCode() !== 200) {
                $body = $response->getBody()->getContents();

                $logger?->error(
                    'LLM request failed on {model} ({status} {reason})',
                    [
                        'provider' => static::class,
                        'model' => $this->model->name,
                        'status' => $response->getStatusCode(),
                        'reason' => $response->getReasonPhrase(),
                        'body_excerpt' => $body,
                    ]
                );

                throw new ProviderException(
                    sprintf(
                        'Invalid response (%d %s)',
                        $response->getStatusCode(),
                        $response->getReasonPhrase(),
                    ),
                    $body,
                );
            }

            $json = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);

            $usage = new Usage(
                promptTokens: $json['usage']['prompt_tokens'] ?? 0,
                completionTokens: $json['usage']['completion_tokens'] ?? 0,
                totalTokens: $json['usage']['total_tokens'] ?? 0,
                cachedTokens: $json['usage']['prompt_tokens_details']['cached_tokens'] ?? 0,
            );
            $totalUsage->addUsage($usage);
            $this->usage->addUsage($usage);

            $choices = new Choices(
                ...array_map(
                    fn(array $choiceData) => new Choice(
                        message: MessageFactory::create($choiceData['message'] ?? []),
                        finishReason: FinishReason::parse($choiceData['finish_reason'] ?? ''),
                        index: $choiceData['index'] ?? 0,
                    ),
                    $json['choices']
                )
            );

            $preferredChoice = $choices->getPreferredChoice();

            $logger?->info(
                'LLM completion on {model} ({total_tokens} tokens, finish: {finish_reason})',
                [
                    'provider' => static::class,
                    'model' => $this->model->name,
                    'prompt_tokens' => $usage->getPromptTokens(),
                    'completion_tokens' => $usage->getCompletionTokens(),
                    'total_tokens' => $usage->getTotalTokens(),
                    'cached_tokens' => $usage->getCachedTokens(),
                    'cost' => $this->model->computeCost($usage),
                    'finish_reason' => $preferredChoice->finishReason?->value,
                    'tool_calls_count' => $preferredChoice->message instanceof AssistantMessage
                        ? count($preferredChoice->message->getToolCalls())
                        : 0,
                ]
            );

            $completion = $completion->withNewMessage($choices);

            if ($preferredChoice->finishReason === FinishReason::TOOL_CALLS
                && $preferredChoice->message instanceof AssistantMessage
                && $preferredChoice->message->hasToolCalls()
                && null !== $tools
            ) {
                foreach ($preferredChoice->message->getToolCalls() as $toolCall) {
                    $logger?->debug(
                        'Executing tool {tool_name}',
                        [
                            'tool_name' => $toolCall->name,
                            'tool_call_id' => $toolCall->id,
                            'arguments' => $toolCall->arguments,
                        ]
                    );

                    $toolResult = $tools->execute($toolCall);
                    $completion = $completion->withNewMessage($toolResult);
                }

                continue;
            }

            return new CompletionResponse(
                completion: $completion,
                usage: $totalUsage,
            );
        } while ($iteration < $maxIterations);

        throw new RuntimeException(
            sprintf('Max tool iterations (%d) exceeded', $maxIterations)
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

        return [
            ...$this->getPayloadBuilder()->build(
                $completion,
                new BuildContext(provider: $this),
            ),
            ...$this->extraBody,
        ];
    }

    /**
     * Get extra body parameters merged at the root of the request payload.
     *
     * @return array
     */
    public function getExtraBody(): array
    {
        return $this->extraBody;
    }

    protected function getPayloadBuilder(): PayloadBuilder
    {
        return new PayloadBuilder($this->getPayloadBuilders());
    }

    /**
     * @return iterable<BuilderInterface>
     */
    protected function getPayloadBuilders(): iterable
    {
        return [];
    }

    #[Override]
    public function getMaxContextTokens(): ?int
    {
        return $this->model->maxContextTokens;
    }

    #[Override]
    public function getMaxOutputTokens(): ?int
    {
        return $this->model->maxOutputTokens;
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
