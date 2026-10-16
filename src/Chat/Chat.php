<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\ConversationInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Model\ModelInterface;
use ByCerfrance\LlmApiLib\Provider\ChatProviderInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequest;
use ByCerfrance\LlmApiLib\Tool\InvocationRegistry;
use ByCerfrance\LlmApiLib\Tool\InvocationRegistryInterface;
use ByCerfrance\LlmApiLib\Tool\ToolCollectionInterface;
use ByCerfrance\LlmApiLib\Tool\ToolExecutor;
use ByCerfrance\LlmApiLib\Tool\ToolResult;
use Closure;
use Override;
use RuntimeException;

readonly class Chat implements ChatInterface
{
    /** @param Closure(): InvocationRegistryInterface|null $registryFactory Fresh registry per execution by default. */
    public function __construct(
        protected ChatProviderInterface $provider,
        protected ModelInterface $model,
        private ?int $maxTokens = null,
        private int|float|null $temperature = null,
        private int|float|null $topP = null,
        protected ToolExecutor $toolExecutor = new ToolExecutor(),
        protected ?Closure $registryFactory = null,
    ) {
        // Apply the same validation to defaults and explicit request options.
        new ChatRequest(new Conversation(), $maxTokens, $temperature, $topP);
    }

    protected function resolveRequest(ChatRequestInterface|string $request): ChatRequestInterface
    {
        if (is_string($request)) {
            $request = new ChatRequest(new Conversation([$request]));
        }

        return new ChatRequest(
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
    }

    #[Override]
    public function chat(ChatRequestInterface|string $request): ChatResponseInterface
    {
        $resolved = $this->resolveRequest($request);
        $registry = $this->createRegistry($resolved);
        $turns = [];
        for ($round = 0; ; $round++) {
            $result = $this->provider->complete(new ProviderChatRequest($this->model, $resolved));
            $turns[] = $result;
            $conversation = $resolved->getConversation()->withNewMessage($result->getMessage());
            if ($result->getToolCalls() === []) {
                return new ChatResponse($conversation, $result, $turns);
            }
            $tools = $this->getExecutableTools($resolved, $round);
            foreach ($result->getToolCalls() as $call) {
                $conversation = $conversation->withNewMessage(
                    $this->toolExecutor->execute($call, $tools, $registry, $resolved->getToolErrorPolicy()),
                );
            }
            $resolved = $this->continueAfterTools($resolved, $conversation);
        }
    }

    /** Creates the invocation registry of one execution, restored from completed history. */
    protected function createRegistry(ChatRequestInterface $request): InvocationRegistryInterface
    {
        $registry = $this->registryFactory !== null ? ($this->registryFactory)() : new InvocationRegistry();
        $this->restoreCompletedInvocations($request->getConversation(), $registry);

        return $registry;
    }

    /** @throws RuntimeException When the provider requests tools that may not be executed. */
    protected function getExecutableTools(ChatRequestInterface $request, int $round): ToolCollectionInterface
    {
        $tools = $request->getTools();
        if ($tools === null || $request->getToolChoice() === ToolChoice::NONE) {
            throw new RuntimeException('The provider requested tools when tool execution was not enabled.');
        }
        if ($round >= $request->getMaxToolIterations()) {
            throw new RuntimeException(sprintf('Maximum tool iterations (%d) exceeded.', $request->getMaxToolIterations()));
        }

        return $tools;
    }

    /** A required tool choice is satisfied once tools ran; later turns may answer freely. */
    protected function continueAfterTools(ChatRequestInterface $request, ConversationInterface $conversation): ChatRequestInterface
    {
        $request = $request->withConversation($conversation);

        return $request->getToolChoice() === ToolChoice::REQUIRED ? $request->withToolChoice(ToolChoice::AUTO) : $request;
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
