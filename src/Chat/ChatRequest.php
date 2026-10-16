<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Chat\Conversation\ConversationInterface;
use ByCerfrance\LlmApiLib\Chat\ResponseFormat\ResponseFormatInterface;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Tool\ToolCollectionInterface;
use ByCerfrance\LlmApiLib\Tool\ToolErrorPolicy;
use InvalidArgumentException;
use Override;

readonly class ChatRequest implements ChatRequestInterface
{
    public function __construct(
        private ConversationInterface $conversation,
        private ?int $maxTokens = null,
        private int|float|null $temperature = null,
        private int|float|null $topP = null,
        private ?ResponseFormatInterface $responseFormat = null,
        private ?ReasoningEffort $reasoningEffort = null,
        private ?ServiceTier $serviceTier = null,
        private ?int $seed = null,
        private ?ToolCollectionInterface $tools = null,
        private int $maxToolIterations = 10,
        private ToolErrorPolicy $toolErrorPolicy = ToolErrorPolicy::STOP,
        private ?ToolChoice $toolChoice = null,
        private ?bool $parallelToolCalls = null,
        private ?array $outputModalities = null,
        private ?array $imageConfig = null,
    ) {
        if ($outputModalities === [] || ($outputModalities !== null && !array_is_list($outputModalities))) {
            throw new InvalidArgumentException('Output modalities must be a non-empty list or null.');
        }
        foreach ($outputModalities ?? [] as $modality) {
            if (!$modality instanceof OutputModality) {
                throw new InvalidArgumentException('Expected OutputModality values.');
            }
        }
        foreach ($imageConfig ?? [] as $key => $value) {
            if (!in_array($key, ['aspectRatio', 'imageSize'], true) || !is_string($value) || $value === '') {
                throw new InvalidArgumentException('Invalid native image configuration.');
            }
        }
        if ($maxToolIterations < 0) {
            throw new InvalidArgumentException('Maximum tool iterations must not be negative.');
        }
        if ($maxTokens !== null && $maxTokens < 1) {
            throw new InvalidArgumentException('Maximum tokens must be positive.');
        }
        if ($temperature !== null && (!is_finite((float) $temperature) || $temperature < 0)) {
            throw new InvalidArgumentException('Temperature must be finite and non-negative.');
        }
        if ($topP !== null && (!is_finite((float) $topP) || $topP < 0 || $topP > 1)) {
            throw new InvalidArgumentException('Top P must be between zero and one.');
        }
    }

    #[Override]
    public function getConversation(): ConversationInterface
    {
        return $this->conversation;
    }

    #[Override]
    public function withConversation(ConversationInterface $conversation): ChatRequestInterface
    {
        return $this->copy(['conversation' => $conversation]);
    }

    #[Override]
    public function getMaxTokens(): ?int
    {
        return $this->maxTokens;
    }

    #[Override]
    public function withMaxTokens(?int $maxTokens): ChatRequestInterface
    {
        return $this->copy(['maxTokens' => $maxTokens]);
    }

    #[Override]
    public function getTemperature(): int|float|null
    {
        return $this->temperature;
    }

    #[Override]
    public function withTemperature(int|float|null $temperature): ChatRequestInterface
    {
        return $this->copy(['temperature' => $temperature]);
    }

    #[Override]
    public function getTopP(): int|float|null
    {
        return $this->topP;
    }

    #[Override]
    public function withTopP(int|float|null $topP): ChatRequestInterface
    {
        return $this->copy(['topP' => $topP]);
    }

    #[Override]
    public function getResponseFormat(): ?ResponseFormatInterface
    {
        return $this->responseFormat;
    }

    #[Override]
    public function withResponseFormat(?ResponseFormatInterface $responseFormat): ChatRequestInterface
    {
        return $this->copy(['responseFormat' => $responseFormat]);
    }

    #[Override]
    public function getReasoningEffort(): ?ReasoningEffort
    {
        return $this->reasoningEffort;
    }

    #[Override]
    public function withReasoningEffort(?ReasoningEffort $reasoningEffort): ChatRequestInterface
    {
        return $this->copy(['reasoningEffort' => $reasoningEffort]);
    }

    #[Override]
    public function getServiceTier(): ?ServiceTier
    {
        return $this->serviceTier;
    }

    #[Override]
    public function withServiceTier(?ServiceTier $serviceTier): ChatRequestInterface
    {
        return $this->copy(['serviceTier' => $serviceTier]);
    }

    #[Override]
    public function getSeed(): ?int
    {
        return $this->seed;
    }

    #[Override]
    public function withSeed(?int $seed): ChatRequestInterface
    {
        return $this->copy(['seed' => $seed]);
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        $capabilities = $this->responseFormat?->requiredCapabilities() ?? [];
        foreach ($this->outputModalities ?? [OutputModality::TEXT] as $modality) {
            $capabilities[] = $modality === OutputModality::IMAGE ? Capability::IMAGE_OUTPUT : Capability::TEXT;
        }
        foreach ($this->conversation as $message) {
            array_push($capabilities, ...$message->requiredCapabilities());
        }
        if ($this->reasoningEffort !== null) {
            $capabilities[] = Capability::REASONING;
        }
        if (($this->tools !== null && count($this->tools) > 0) || $this->toolChoice !== null || $this->parallelToolCalls !== null) {
            $capabilities[] = Capability::TOOLS;
        }

        return array_values(array_unique($capabilities, SORT_REGULAR));
    }

    #[Override]
    public function getTools(): ?ToolCollectionInterface
    {
        return $this->tools;
    }

    #[Override]
    public function withTools(?ToolCollectionInterface $tools): ChatRequestInterface
    {
        return $this->copy(['tools' => $tools]);
    }

    #[Override]
    public function getMaxToolIterations(): int
    {
        return $this->maxToolIterations;
    }

    #[Override]
    public function withMaxToolIterations(int $maxToolIterations): ChatRequestInterface
    {
        return $this->copy(['maxToolIterations' => $maxToolIterations]);
    }

    #[Override]
    public function getToolErrorPolicy(): ToolErrorPolicy
    {
        return $this->toolErrorPolicy;
    }

    #[Override]
    public function withToolErrorPolicy(ToolErrorPolicy $toolErrorPolicy): ChatRequestInterface
    {
        return $this->copy(['toolErrorPolicy' => $toolErrorPolicy]);
    }

    #[Override]
    public function getToolChoice(): ?ToolChoice
    {
        return $this->toolChoice;
    }

    #[Override]
    public function withToolChoice(?ToolChoice $toolChoice): ChatRequestInterface
    {
        return $this->copy(['toolChoice' => $toolChoice]);
    }

    #[Override]
    public function getParallelToolCalls(): ?bool
    {
        return $this->parallelToolCalls;
    }

    #[Override]
    public function withParallelToolCalls(?bool $parallelToolCalls): ChatRequestInterface
    {
        return $this->copy(['parallelToolCalls' => $parallelToolCalls]);
    }

    /** @param array<string, mixed> $changes */
    private function copy(array $changes): self
    {
        return new self(...array_replace(get_object_vars($this), $changes));
    }

    #[Override]
    public function getOutputModalities(): ?array
    {
        return $this->outputModalities;
    }

    #[Override]
    public function withOutputModalities(?array $modalities): ChatRequestInterface
    {
        return $this->copy(['outputModalities' => $modalities]);
    }

    #[Override]
    public function getImageConfig(): ?array
    {
        return $this->imageConfig;
    }

    #[Override]
    public function withImageConfig(?array $config): ChatRequestInterface
    {
        return $this->copy(['imageConfig' => $config]);
    }
}
