<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider\OpenAiCompatible;

use Berlioz\Http\Message\Request;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\ProviderAssistantMessage;
use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Chat\OutputModality;
use ByCerfrance\LlmApiLib\Chat\ToolChoice;
use ByCerfrance\LlmApiLib\Model\ParameterPolicy;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\ModelChatBuilder;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Provider\ChatProviderInterface;
use ByCerfrance\LlmApiLib\Provider\ChatResult;
use ByCerfrance\LlmApiLib\Provider\ChatResultInterface;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\JsonTransport;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequestInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use JsonException;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SensitiveParameter;

abstract readonly class AbstractOpenAiCompatible implements ChatProviderInterface
{
    private string $id;
    private JsonTransport $transport;

    /** @param array<string, mixed> $extraBody Additional provider-specific root fields. */
    public function __construct(
        #[SensitiveParameter] private string $apiKey,
        ClientInterface $client,
        ?string $id = null,
        private array $extraBody = [],
        private ?LoggerInterface $logger = null,
        private ParameterPolicy $parameterPolicy = ParameterPolicy::STRICT,
    ) {
        if ($parameterPolicy === ParameterPolicy::OMIT_UNSUPPORTED && $logger === null) {
            throw new InvalidArgumentException('Omitting unsupported parameters requires a logger to report changes.');
        }
        $this->id = $id ?? substr(strrchr(static::class, '\\'), 1);
        $this->transport = new JsonTransport($client);
        $reserved = ['model', 'messages', 'stream', 'n', 'tools', 'tool_choice', 'functions', 'function_call'];
        foreach ($reserved as $field) {
            if (array_key_exists($field, $extraBody)) {
                throw new InvalidArgumentException(sprintf('Provider extension "%s" is reserved.', $field));
            }
        }
    }

    #[Override]
    public function getId(): string
    {
        return $this->id;
    }

    protected function createHttpRequest(ProviderChatRequestInterface $request, bool $stream = false): Request
    {
        $input = $request->getRequest();
        if ($input->getImageConfig() !== null || in_array(OutputModality::IMAGE, $input->getOutputModalities() ?? [], true)) {
            throw new InvalidArgumentException('Use a native image-capable chat provider for image output.');
        }
        foreach ($input->getConversation() as $message) {
            if ($message instanceof ProviderAssistantMessage) {
                throw new InvalidArgumentException('Native continuation cannot be sent to an OpenAI-compatible chat endpoint.');
            }
        }
        if (count($input->getConversation()) === 0) {
            throw new InvalidArgumentException('A chat request must contain at least one message.');
        }
        $tools = $input->getTools();
        if (($tools === null || count($tools) === 0)
            && (($input->getToolChoice() !== null && $input->getToolChoice() !== ToolChoice::NONE)
                || $input->getParallelToolCalls() !== null)) {
            throw new InvalidArgumentException('Tool choice and parallel calls require configured tools.');
        }
        foreach ($input->requiredCapabilities() as $capability) {
            if ($request->getModel()->supports($capability) === false) {
                throw new InvalidArgumentException(sprintf('Model "%s" does not support %s.', $request->getModel()->getName(), $capability->value));
            }
        }

        $body = array_filter([
            'model' => $request->getModel()->getName(),
            'messages' => iterator_to_array($input->getConversation(), false),
            'stream' => $stream,
            'max_completion_tokens' => $input->getMaxTokens(),
            'temperature' => $input->getTemperature(),
            'top_p' => $input->getTopP(),
            'response_format' => $input->getResponseFormat(),
            'reasoning_effort' => $input->getReasoningEffort(),
            'service_tier' => $input->getServiceTier(),
            'seed' => $input->getSeed(),
            'tools' => $input->getTools() !== null && count($input->getTools()) > 0 ? $input->getTools() : null,
            'tool_choice' => $input->getToolChoice(),
            'parallel_tool_calls' => $input->getParallelToolCalls(),
        ], static fn(mixed $value): bool => $value !== null);

        if ($stream) {
            $body['stream_options'] = ['include_usage' => true];
        }

        foreach ($this->extraBody as $field => $value) {
            if (array_key_exists($field, $body)) {
                throw new InvalidArgumentException(sprintf('Provider extension "%s" conflicts with a configured field.', $field));
            }
            $body[$field] = $value;
        }

        $maxTokens = $body['max_completion_tokens'] ?? null;
        $temperature = $body['temperature'] ?? null;
        $topP = $body['top_p'] ?? null;
        if (($maxTokens !== null && !is_int($maxTokens))
            || ($temperature !== null && !is_int($temperature) && !is_float($temperature))
            || ($topP !== null && !is_int($topP) && !is_float($topP))) {
            throw new InvalidArgumentException('Provider extensions contain invalid generation parameter types.');
        }
        // Extensions must not bypass validation of normalized generation fields.
        new ChatRequest($input->getConversation(), $maxTokens, $temperature, $topP);
        $body = array_filter($body, static fn(mixed $value): bool => $value !== null);

        try {
            $httpRequest = new Request(
                method: Request::HTTP_METHOD_POST,
                uri: $this->createUri(),
                body: json_encode((new PayloadBuilder($this->getPayloadBuilders()))->build(
                    new ChatPayload($body),
                    new BuildContext(provider: $this, model: $request->getModel()),
                ), JSON_THROW_ON_ERROR),
                headers: [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The chat request cannot be encoded as JSON.', previous: $exception);
        }

        return $httpRequest;
    }

    #[Override]
    public function complete(ProviderChatRequestInterface $request): ChatResultInterface
    {
        $httpRequest = $this->createHttpRequest($request);
        $this->logger?->debug('Sending chat request.', ['provider' => $this->id, 'model' => $request->getModel()->getName()]);
        $response = $this->transport->send($this->id, $httpRequest);
        $requestId = $response->getHeaderLine('x-request-id') ?: null;
        $data = $this->transport->decode($response, $this->id);

        try {
            return $this->parseResult($data, $requestId);
        } catch (RuntimeException $exception) {
            throw new ProviderException($exception->getMessage(), previous: $exception,
                kind: FailureKind::INVALID_RESPONSE, requestId: $requestId, providerId: $this->id);
        }
    }

    /**
     * Parse a decoded chat completion, synchronous or assembled from a stream.
     *
     * @param array<mixed> $data
     *
     * @throws RuntimeException On an invalid or unsupported response.
     */
    protected function parseResult(array $data, ?string $requestId): ChatResultInterface
    {
        if (!isset($data['choices']) || !is_array($data['choices'])
            || count($data['choices']) !== 1 || !isset($data['choices'][0]) || !is_array($data['choices'][0])) {
            throw new RuntimeException('Expected exactly one chat response choice.');
        }
        $choice = $data['choices'][0];
        $message = $choice['message'] ?? null;
        if (!is_array($message) || ($message['role'] ?? null) !== 'assistant'
            || (isset($message['content']) && !is_string($message['content']))
            || !empty($message['function_call'])) {
            throw new RuntimeException('Expected a text or function-calling assistant response.');
        }
        $toolCalls = $message['tool_calls'] ?? [];
        if (!is_array($toolCalls) || !array_is_list($toolCalls)) {
            throw new RuntimeException('Invalid tool call list in provider response.');
        }
        $parsedCalls = [];
        foreach ($toolCalls as $call) {
            if (!is_array($call)) {
                throw new RuntimeException('Invalid tool call in provider response.');
            }
            $parsed = ToolCall::fromArray($call);
            if (isset($parsedCalls[$parsed->id])) {
                throw new RuntimeException('Duplicate tool invocation ID in a provider turn.');
            }
            $parsedCalls[$parsed->id] = $parsed;
        }
        if ($parsedCalls === [] && !is_string($message['content'] ?? null)) {
            throw new RuntimeException('The provider returned neither text nor tool calls.');
        }
        $finishReason = $choice['finish_reason'] ?? null;
        if ($finishReason !== null && !is_string($finishReason)) {
            throw new RuntimeException('Invalid finish reason in provider response.');
        }
        if ($finishReason === 'tool_calls' && $parsedCalls === []) {
            throw new RuntimeException('Tool-call finish reason requires tool invocations.');
        }
        $usage = $data['usage'] ?? [];
        if (!is_array($usage)) {
            throw new RuntimeException('Invalid usage in provider response.');
        }
        $details = $usage['prompt_tokens_details'] ?? [];
        if (!is_array($details)) {
            throw new RuntimeException('Invalid prompt token details in provider response.');
        }
        $model = $data['model'] ?? null;
        if ($model !== null && !is_string($model)) {
            throw new RuntimeException('Invalid model identifier in provider response.');
        }

        return new ChatResult(
            message: new AssistantMessage($message['content'] ?? null, array_values($parsedCalls)),
            usage: new TokenUsage(
                promptTokens: $this->parseTokens($usage, 'prompt_tokens'),
                completionTokens: $this->parseTokens($usage, 'completion_tokens'),
                totalTokens: $this->parseTokens($usage, 'total_tokens'),
                cachedTokens: $this->parseTokens($details, 'cached_tokens'),
            ),
            finishReason: $finishReason === null ? null : FinishReason::parse($finishReason),
            providerId: $this->id,
            modelName: $model,
            requestId: $requestId,
        );
    }

    abstract protected function createUri(): UriInterface;

    /** @return iterable<BuilderInterface> */
    protected function getPayloadBuilders(): iterable
    {
        return [new ModelChatBuilder($this->parameterPolicy, $this->logger), new ChatContentBuilder()];
    }

    /** @param array<string, mixed> $usage */
    private function parseTokens(array $usage, string $field): ?int
    {
        $value = $usage[$field] ?? null;
        if ($value !== null && (!is_int($value) || $value < 0)) {
            throw new RuntimeException(sprintf('Invalid token count "%s" in provider response.', $field));
        }

        return $value;
    }
}
