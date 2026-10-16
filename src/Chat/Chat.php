<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Tool\InvocationRegistry;
use ByCerfrance\LlmApiLib\Completion\Tool\InvocationRegistryInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Completion\ToolChoice;
use ByCerfrance\LlmApiLib\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Conversation\ConversationInterface;
use ByCerfrance\LlmApiLib\Model\ModelInterface;
use ByCerfrance\LlmApiLib\Provider\ChatProviderInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use Closure;
use Override;
use RuntimeException;

readonly class Chat implements ChatInterface
{
    /** @param Closure(): InvocationRegistryInterface|null $registryFactory Fresh registry per execution by default. */
    public function __construct(
        private ChatProviderInterface $provider,
        private ModelInterface $model,
        private ?int $maxTokens = null,
        private int|float|null $temperature = null,
        private int|float|null $topP = null,
        private ToolExecutor $toolExecutor = new ToolExecutor(),
        private ?Closure $registryFactory = null,
    ) {
        // Apply the same validation to defaults and explicit request options.
        new ChatRequest(new Conversation(), $maxTokens, $temperature, $topP);
    }

    #[Override]
    public function chat(ChatRequestInterface|string $request): ChatResponseInterface
    {
        if (is_string($request)) {
            $request = new ChatRequest(new Conversation([$request]));
        }

        $resolved = new ChatRequest(
            conversation: $request->getConversation(),
            maxTokens: $request->getMaxTokens() ?? $this->maxTokens,
            temperature: $request->getTemperature() ?? $this->temperature,
            topP: $request->getTopP() ?? $this->topP,
            responseFormat: $request->getResponseFormat(),
            reasoningEffort: $request->getReasoningEffort(),
            serviceTier: $request->getServiceTier(),
            seed: $request->getSeed(),
            tools: $request->getTools(),
            maxToolIterations: $request->getMaxToolIterations(),
            toolErrorPolicy: $request->getToolErrorPolicy(),
            toolChoice: $request->getToolChoice(),
            parallelToolCalls: $request->getParallelToolCalls(),
            outputModalities: $request->getOutputModalities(),
            imageConfig: $request->getImageConfig(),
        );
        $registry = $this->registryFactory !== null ? ($this->registryFactory)() : new InvocationRegistry();
        $this->restoreCompletedInvocations($resolved->getConversation(), $registry);
        $turns = [];
        $rounds = 0;
        while (true) {
            $result = $this->provider->complete(new ProviderChatRequest($this->model, $resolved));
            $turns[] = $result;
            $conversation = $resolved->getConversation()->withNewMessage($result->getMessage());
            $calls = $result->getToolCalls();
            if ($calls === []) {
                return new ChatResponse($conversation, $result, $turns);
            }
            $tools = $resolved->getTools();
            if ($tools === null || $resolved->getToolChoice() === ToolChoice::NONE) {
                throw new RuntimeException('The provider requested tools when tool execution was not enabled.');
            }
            if ($rounds >= $resolved->getMaxToolIterations()) {
                throw new RuntimeException(sprintf('Maximum tool iterations (%d) exceeded.', $resolved->getMaxToolIterations()));
            }
            foreach ($calls as $call) {
                $toolResult = $this->toolExecutor->execute($call, $tools, $registry, $resolved->getToolErrorPolicy());
                $conversation = $conversation->withNewMessage($toolResult);
            }
            $resolved = $resolved->withConversation($conversation);
            if ($resolved->getToolChoice() === ToolChoice::REQUIRED) {
                $resolved = $resolved->withToolChoice(ToolChoice::AUTO);
            }
            $rounds++;
        }
    }

    private function restoreCompletedInvocations(ConversationInterface $conversation, InvocationRegistryInterface $registry): void
    {
        $pending = [];
        foreach ($conversation as $message) {
            if ($message instanceof AssistantMessage) {
                foreach ($message->getToolCalls() as $call) {
                    if (isset($pending[$call->id])) {
                        throw new RuntimeException('Conversation contains duplicate unresolved tool invocation IDs.');
                    }
                    $pending[$call->id] = $call;
                }
            }
            if ($message instanceof ToolResult) {
                $id = $message->getToolCallId();
                if (!isset($pending[$id])) {
                    throw new RuntimeException(sprintf('Tool result "%s" has no preceding invocation.', $id));
                }
                $registry->record($pending[$id], $message);
                unset($pending[$id]);
            }
        }
        if ($pending !== []) {
            throw new RuntimeException('Conversation contains tool invocations without results.');
        }
    }
}
