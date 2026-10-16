<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat\Streaming;

use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;
use ByCerfrance\LlmApiLib\Chat\ChatResponse;
use ByCerfrance\LlmApiLib\Model\ModelInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Provider\StreamingChatProviderInterface;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use Closure;
use Override;
use RuntimeException;

readonly class StreamingChat extends Chat implements StreamingChatInterface
{
    private StreamingChatProviderInterface $streamingProvider;

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
        $this->streamingProvider = $provider;
    }

    #[Override]
    public function stream(ChatRequestInterface|string $request): ChatStream
    {
        return new ChatStream(function (StreamControl $control) use ($request): iterable {
            $request = $this->toRequest($request);
            $resolved = $this->resolveRequest($request);
            $registry = $this->createRegistry($resolved);
            $turns = [];
            for ($round = 0; !$control->isClosed(); $round++) {
                $result = null;
                foreach ($this->streamingProvider->stream(new ProviderChatRequest($this->model, $resolved), $control) as $event) {
                    if ($control->isClosed()) { return; }
                    if ($event->result !== null) { $result = $event->result; }
                    else { yield new ChatEvent($event->type, $event->text, $event->toolIndex); }
                }
                if ($control->isClosed()) { return; }
                if ($result === null) { throw new RuntimeException('Stream ended without a completed provider turn.'); }
                $turns[] = $result;
                $conversation = $resolved->getConversation()->withNewMessage($result->getMessage());
                if ($result->getToolCalls() === []) {
                    yield new ChatEvent('completed', response: new ChatResponse($conversation, $result, $turns, $request));
                    return;
                }
                $tools = $this->getExecutableTools($resolved, $round);
                foreach ($result->getToolCalls() as $call) {
                    yield new ChatEvent('tool.started', toolCall: $call);
                    if ($control->isClosed()) { return; }
                    $toolResult = $this->toolExecutor->execute($call, $tools, $registry, $resolved->getToolErrorPolicy());
                    $conversation = $conversation->withNewMessage($toolResult);
                    yield new ChatEvent('tool.completed', toolCall: $call, toolResult: $toolResult);
                    if ($control->isClosed()) { return; }
                }
                $resolved = $this->continueAfterTools($resolved, $conversation);
            }
        });
    }
}
