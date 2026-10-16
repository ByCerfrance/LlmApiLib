<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Message;

use ByCerfrance\LlmApiLib\Completion\Content\ContentInterface;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;

/** Native continuation is scoped to its originating protocol, provider and model. */
readonly class ProviderAssistantMessage extends AssistantMessage
{
    private string $nativeJson;

    /** @param list<ToolCall> $toolCalls @param array<string, mixed> $nativeContent */
    public function __construct(
        ContentInterface $content,
        array $toolCalls,
        public string $protocol,
        public string $providerId,
        public string $modelName,
        array $nativeContent,
    ) {
        parent::__construct($content, $toolCalls);
        $this->nativeJson = json_encode($nativeContent, JSON_THROW_ON_ERROR);
    }

    public function getNativeContent(): array
    {
        return (array) json_decode($this->nativeJson, flags: JSON_THROW_ON_ERROR);
    }
}
