<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Chat\OutputModality;
use ByCerfrance\LlmApiLib\Completion\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Completion\Content\ContentInterface;
use ByCerfrance\LlmApiLib\Completion\Content\ImageContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Completion\FinishReason;
use ByCerfrance\LlmApiLib\Completion\Message\AssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\ProviderAssistantMessage;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCall;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolResult;
use ByCerfrance\LlmApiLib\Completion\Tool\ToolSchema;
use ByCerfrance\LlmApiLib\Completion\ToolChoice;
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\Builder\ModelChatBuilder;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use Override;
use Psr\Http\Client\ClientInterface;
use SensitiveParameter;
use stdClass;
use Throwable;

/** Gemini v1beta generateContent with full client-side native history replay. */
readonly class GoogleNative implements ChatProviderInterface
{
    private const PROTOCOL = 'google.generateContent.v1beta';
    private JsonTransport $transport;

    /** @param array<string, mixed> $generationConfig Additional native generation options. */
    public function __construct(
        #[SensitiveParameter] private string $apiKey,
        ClientInterface $client,
        private string $id = 'GoogleNative',
        private array $generationConfig = [],
    ) {
        foreach (['candidateCount', 'responseModalities', 'imageConfig', 'temperature', 'topP', 'maxOutputTokens', 'seed', 'responseMimeType', 'responseJsonSchema'] as $key) {
            if (array_key_exists($key, $generationConfig)) {
                throw new InvalidArgumentException(sprintf('Configure native option "%s" through ChatRequest.', $key));
            }
        }
        $this->transport = new JsonTransport($client);
    }

    #[Override]
    public function getId(): string
    {
        return $this->id;
    }

    #[Override]
    public function complete(ProviderChatRequestInterface $request): ChatResultInterface
    {
        $input = $request->getRequest();
        foreach ($input->requiredCapabilities() as $capability) {
            if ($request->getModel()->supports($capability) === false) {
                throw new InvalidArgumentException(sprintf('Model does not support %s.', $capability->value));
            }
        }
        if ($input->getServiceTier() !== null || $input->getReasoningEffort() !== null || $input->getParallelToolCalls() !== null) {
            throw new InvalidArgumentException('Native Google service tier, reasoning effort and parallel-call mappings are not configured; use native generationConfig for thinking settings.');
        }
        if ($input->getImageConfig() !== null && !in_array(OutputModality::IMAGE, $input->getOutputModalities() ?? [], true)) {
            throw new InvalidArgumentException('Image configuration requires explicit IMAGE output modality.');
        }
        $normalized = (new ModelChatBuilder())->build(new ChatPayload(array_filter([
            'temperature' => $input->getTemperature(), 'top_p' => $input->getTopP(),
            'max_completion_tokens' => $input->getMaxTokens(), 'seed' => $input->getSeed(), 'response_format' => $input->getResponseFormat(),
        ], static fn(mixed $value): bool => $value !== null)), new BuildContext($this, $request->getModel()))->jsonSerialize();
        $config = $this->generationConfig;
        foreach (['temperature' => 'temperature', 'top_p' => 'topP', 'max_completion_tokens' => 'maxOutputTokens', 'seed' => 'seed'] as $key => $native) {
            if (isset($normalized[$key])) {
                $config[$native] = $normalized[$key];
            }
        }
        if ($input->getOutputModalities() !== null) {
            $config['responseModalities'] = array_map(static fn(OutputModality $value): string => $value->value, $input->getOutputModalities());
        }
        if ($input->getImageConfig() !== null) {
            $config['imageConfig'] = $input->getImageConfig();
        }
        $format = $normalized['response_format'] ?? null;
        if ($format !== null) {
            $config['responseMimeType'] = ($format['type'] ?? null) === 'text' ? 'text/plain' : 'application/json';
            if (($format['type'] ?? null) === 'json_schema') {
                $config['responseJsonSchema'] = $format['json_schema']['schema'];
            }
        }
        $validation = $config;
        foreach (['topK' => 'top_k', 'topP' => 'top_p', 'maxOutputTokens' => 'max_completion_tokens'] as $nativeKey => $key) {
            if (isset($validation[$nativeKey])) {
                $validation[$key] = $validation[$nativeKey];
                unset($validation[$nativeKey]);
            }
        }
        (new ModelChatBuilder())->build(new ChatPayload($validation), new BuildContext($this, $request->getModel()));
        $contents = [];
        $system = [];
        $calls = [];
        foreach ($input->getConversation() as $message) {
            if ($message instanceof AssistantMessage) {
                foreach ($message->getToolCalls() as $call) {
                    $calls[$call->id] = $call;
                }
            }
            if ($message instanceof ProviderAssistantMessage) {
                if ($message->protocol !== self::PROTOCOL || $message->providerId !== $this->id || $message->modelName !== $request->getModel()->getName()) {
                    throw new InvalidArgumentException('Native continuation belongs to a different protocol, provider or model.');
                }
                $contents[] = $message->getNativeContent();
                continue;
            }
            if ($message instanceof ToolResult) {
                $call = $calls[$message->getToolCallId()] ?? throw new InvalidArgumentException('Tool response has no native invocation.');
                $functionResponse = ['name' => $call->name, 'response' => ['result' => (string) $message->getContent()]];
                if (isset($call->additionalFields['google_native_id'])) {
                    $functionResponse['id'] = $call->additionalFields['google_native_id'];
                }
                $lastIndex = array_key_last($contents);
                if ($lastIndex !== null && ($contents[$lastIndex]['role'] ?? null) === 'user'
                    && is_array($contents[$lastIndex]['parts'][0] ?? null) && isset($contents[$lastIndex]['parts'][0]['functionResponse'])) {
                    $contents[$lastIndex]['parts'][] = ['functionResponse' => $functionResponse];
                } else {
                    $contents[] = ['role' => 'user', 'parts' => [['functionResponse' => $functionResponse]]];
                }
                continue;
            }
            $parts = $this->parts($message->getContent());
            if ($message->getRole() === RoleEnum::SYSTEM) {
                if ($contents !== []) {
                    throw new InvalidArgumentException('Native system instructions must precede conversation messages.');
                }
                foreach ($parts as $part) {
                    if (!isset($part['text'])) {
                        throw new InvalidArgumentException('Native system instructions must be text.');
                    }
                }
                array_push($system, ...$parts);
                continue;
            }
            if ($message instanceof AssistantMessage && $message->hasToolCalls()) {
                throw new InvalidArgumentException('Function-call history requires native continuation metadata.');
            }
            if (!in_array($message->getRole(), [RoleEnum::USER, RoleEnum::ASSISTANT], true)) {
                throw new InvalidArgumentException('Unsupported native message role.');
            }
            $contents[] = ['role' => $message->getRole() === RoleEnum::ASSISTANT ? 'model' : 'user', 'parts' => $parts];
        }
        if ($contents === []) {
            throw new InvalidArgumentException('A native chat requires user content.');
        }
        $body = ['contents' => $contents];
        if ($config !== []) { $body['generationConfig'] = $config; }
        if ($system !== []) { $body['systemInstruction'] = ['parts' => $system]; }
        if ($input->getTools() !== null && count($input->getTools()) > 0) {
            $declarations = [];
            foreach ($input->getTools() as $tool) {
                $declarations[] = ['name' => $tool->getName(), 'description' => $tool->getDescription(), 'parametersJsonSchema' => ToolSchema::normalize($tool->getParameters())];
            }
            $body['tools'] = [['functionDeclarations' => $declarations]];
            if ($input->getToolChoice() !== null) {
                $body['toolConfig'] = ['functionCallingConfig' => ['mode' => match ($input->getToolChoice()) {
                    ToolChoice::AUTO => 'AUTO', ToolChoice::NONE => 'NONE', ToolChoice::REQUIRED => 'ANY',
                }]];
            }
        } elseif ($input->getToolChoice() !== null) {
            throw new InvalidArgumentException('Tool choice requires native tool declarations.');
        }
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($request->getModel()->getName()) . ':generateContent';
        $response = $this->transport->post($this->id, $url, $body, ['x-goog-api-key' => $this->apiKey]);
        $data = $this->transport->decode($response, $this->id);
        try {
            $raw = json_decode((string) $response->getBody(), flags: JSON_THROW_ON_ERROR);
            if (!is_array($raw->candidates ?? null) || count($raw->candidates) !== 1) {
                throw new InvalidArgumentException('Expected one native candidate.');
            }
            $candidate = $raw->candidates[0];
            if (($candidate->finishReason ?? null) === 'CONTINUATION') {
                throw new InvalidArgumentException('Automatic continuation-token resumption is not supported.');
            }
            $native = $candidate->content ?? null;
            if (!$native instanceof stdClass || ($native->role ?? null) !== 'model' || !is_array($native->parts ?? null) || $native->parts === []) {
                throw new InvalidArgumentException('Missing native model content.');
            }
            $visible = [];
            $invocations = [];
            $invocationIds = [];
            foreach ($native->parts as $index => $part) {
                if (!$part instanceof stdClass) { throw new InvalidArgumentException('Invalid native part.'); }
                if (isset($part->text)) {
                    if (!is_string($part->text)) { throw new InvalidArgumentException('Invalid text part.'); }
                    if (!($part->thought ?? false)) { $visible[] = new TextContent($part->text); }
                } elseif (isset($part->inlineData)) {
                    $image = new ImageAsset($part->inlineData->mimeType, $part->inlineData->data);
                    if (!($part->thought ?? false)) { $visible[] = new ImageContent($image); }
                } elseif (isset($part->fileData)) {
                    $visible[] = new ImageContent(new ImageAsset($part->fileData->mimeType, url: $part->fileData->fileUri));
                } elseif (isset($part->functionCall)) {
                    $call = $part->functionCall;
                    $id = $call->id ?? bin2hex(random_bytes(16));
                    if (!is_string($id) || isset($invocationIds[$id])) {
                        throw new InvalidArgumentException('Invalid or duplicate native invocation ID.');
                    }
                    $invocationIds[$id] = true;
                    $invocations[] = ToolCall::fromArray(['id' => $id, 'type' => 'function', 'function' => [
                        'name' => $call->name, 'arguments' => json_encode($call->args ?? new stdClass(), JSON_THROW_ON_ERROR),
                    ], ...(isset($call->id) ? ['google_native_id' => $call->id] : [])]);
                } else {
                    throw new InvalidArgumentException('Unsupported native output part.');
                }
            }
            $usage = $data['usageMetadata'] ?? [];
            if (!is_array($usage) || ($visible === [] && $invocations === [])) {
                throw new InvalidArgumentException('Missing visible output or invalid usage metadata.');
            }
            $tokens = [];
            foreach (['promptTokenCount', 'candidatesTokenCount', 'totalTokenCount', 'cachedContentTokenCount'] as $key) {
                $value = $usage[$key] ?? null;
                if ($value !== null && (!is_int($value) || $value < 0)) { throw new InvalidArgumentException('Invalid native usage.'); }
                $tokens[] = $value;
            }
            $reason = match ($candidate->finishReason ?? null) {
                'STOP' => $invocations === [] ? FinishReason::STOP : FinishReason::TOOL_CALLS,
                'MAX_TOKENS' => FinishReason::LENGTH,
                'SAFETY', 'IMAGE_SAFETY', 'RECITATION' => FinishReason::CONTENT_FILTER,
                default => null,
            };
            return new ChatResult(new ProviderAssistantMessage(new ArrayContent(...$visible), $invocations, self::PROTOCOL, $this->id,
                $request->getModel()->getName(), (array) $native), new TokenUsage(...$tokens), $reason, $this->id,
                $data['modelVersion'] ?? $request->getModel()->getName(), $response->getHeaderLine('x-request-id') ?: ($data['responseId'] ?? null));
        } catch (Throwable $failure) {
            throw new ProviderException('Invalid native Gemini response.', previous: $failure, kind: FailureKind::INVALID_RESPONSE, providerId: $this->id,
                requestId: $response->getHeaderLine('x-request-id') ?: null);
        }
    }

    /** @return list<array<string, mixed>> */
    private function parts(ContentInterface $content): array
    {
        $wire = (new PayloadBuilder())->build($content);
        if (is_string($wire)) { return [['text' => $wire]]; }
        if ($wire === null) { return []; }
        if (!is_array($wire)) { throw new InvalidArgumentException('Unsupported native input content.'); }
        $parts = [];
        foreach (isset($wire['type']) ? [$wire] : $wire as $part) {
            if (($part['type'] ?? null) === 'text') { $parts[] = ['text' => $part['text']]; continue; }
            if (($part['type'] ?? null) === 'input_audio') {
                $parts[] = ['inlineData' => ['mimeType' => 'audio/' . $part['input_audio']['format'], 'data' => $part['input_audio']['data']]];
                continue;
            }
            $url = $part['image_url']['url'] ?? $part['document_url']['url'] ?? null;
            if (!is_string($url) || !preg_match('~^data:([^;,]+);base64,(.+)$~s', $url, $matches) || base64_decode($matches[2], true) === false) {
                throw new InvalidArgumentException('Native media input requires an inline base64 data URI; remote URLs are not downloaded.');
            }
            $parts[] = ['inlineData' => ['mimeType' => $matches[1], 'data' => $matches[2]]];
        }
        return $parts;
    }
}
