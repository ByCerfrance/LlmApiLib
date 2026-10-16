<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Completion\Tool\InvocationRegistry;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Completion\ToolChoice;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Provider\StreamingChatProviderInterface;
use ByCerfrance\LlmApiLib\Model\ModelInterface;
use Closure;
use InvalidArgumentException;
use Override;
use RuntimeException;

readonly class StreamingChat extends Chat implements StreamingChatInterface
{
    public function __construct(
        StreamingChatProviderInterface $provider,
        ModelInterface $model,
        ?int $maxTokens = null,
        int|float|null $temperature = null,
        int|float|null $topP = null,
        ToolExecutor $toolExecutor = new ToolExecutor(),
        ?Closure $registryFactory = null,
    ) {
        parent::__construct($provider, $model, $maxTokens, $temperature, $topP, $toolExecutor, $registryFactory);
    }

    #[Override]
    public function stream(ChatRequestInterface|string $request): ChatStream
    {
        if (!$this->provider instanceof StreamingChatProviderInterface) {
            throw new InvalidArgumentException('StreamingChat requires a streaming provider.');
        }
        return new ChatStream(function (StreamControl $control) use ($request): iterable {
            $resolved = $this->resolveRequest($request);
            $registry = $this->registryFactory !== null ? ($this->registryFactory)() : new InvocationRegistry();
            $this->restoreCompletedInvocations($resolved->getConversation(), $registry);
            $turns = [];
            for ($round = 0; !$control->isClosed(); $round++) {
                $result = null;
                foreach ($this->provider->stream(new ProviderChatRequest($this->model, $resolved), $control) as $event) {
                    if ($control->isClosed()) { return; }
                    if ($event->result !== null) { $result = $event->result; }
                    else { yield new ChatEvent($event->type, $event->text, $event->toolIndex); }
                }
                if ($control->isClosed()) { return; }
                if ($result === null) { throw new RuntimeException('Stream ended without a completed provider turn.'); }
                $turns[] = $result;
                $conversation = $resolved->getConversation()->withNewMessage($result->getMessage());
                if ($result->getToolCalls() === []) {
                    yield new ChatEvent('completed', response: new ChatResponse($conversation, $result, $turns));
                    return;
                }
                if ($round >= $resolved->getMaxToolIterations() || $resolved->getTools() === null || $resolved->getToolChoice() === ToolChoice::NONE) {
                    throw new RuntimeException('Streamed tool execution is disabled or its iteration limit was reached.');
                }
                foreach ($result->getToolCalls() as $call) {
                    yield new ChatEvent('tool.started', toolCall: $call);
                    if ($control->isClosed()) { return; }
                    $toolResult = $this->toolExecutor->execute($call, $resolved->getTools(), $registry, $resolved->getToolErrorPolicy());
                    $conversation = $conversation->withNewMessage($toolResult);
                    yield new ChatEvent('tool.completed', toolCall: $call, toolResult: $toolResult);
                    if ($control->isClosed()) { return; }
                }
                $resolved = $resolved->withConversation($conversation);
                if ($resolved->getToolChoice() === ToolChoice::REQUIRED) { $resolved = $resolved->withToolChoice(ToolChoice::AUTO); }
            }
        });
    }
}
