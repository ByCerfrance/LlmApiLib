<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider\OpenAi;

use ByCerfrance\LlmApiLib\Chat\Streaming\StreamControl;
use ByCerfrance\LlmApiLib\Model\ParameterPolicy;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\ProviderChatRequestInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Provider\ProviderStreamEvent;
use ByCerfrance\LlmApiLib\Provider\StreamingChatProviderInterface;
use ByCerfrance\LlmApiLib\Transport\SseDecoder;
use ByCerfrance\LlmApiLib\Transport\StreamingTransportInterface;
use JsonException;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SensitiveParameter;

readonly class StreamingOpenAi extends OpenAi implements StreamingChatProviderInterface
{
    public function __construct(
        #[SensitiveParameter] string $apiKey,
        ClientInterface $client,
        private StreamingTransportInterface $transport,
        ?string $id = null,
        array $extraBody = [],
        ?LoggerInterface $logger = null,
        ParameterPolicy $parameterPolicy = ParameterPolicy::STRICT,
    ) {
        parent::__construct($apiKey, $client, $id, $extraBody, $logger, $parameterPolicy);
    }

    #[Override]
    public function stream(ProviderChatRequestInterface $request, StreamControl $control): iterable
    {
        if ($control->isClosed()) { return; }
        $httpRequest = $this->createHttpRequest($request, true)->withHeader('Accept', 'text/event-stream');
        $response = null;
        $closer = null;
        try {
            $response = $this->transport->open($httpRequest);
            $closer = $control->onClose($response->close(...));
            $requestId = $response->getHeaderLine('x-request-id') ?: null;
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw ProviderException::fromHttpStatus($this->getId(), $status, $response->getHeaderLine('Retry-After'), $response->getHeaderLine('x-request-id'));
            }
            if (!str_starts_with(strtolower($response->getHeaderLine('Content-Type')), 'text/event-stream')) {
                throw new ProviderException('Expected an SSE response.', kind: FailureKind::INVALID_RESPONSE, providerId: $this->getId());
            }
            $text = '';
            $tools = [];
            $usage = null;
            $model = null;
            $finish = null;
            $done = false;
            foreach ((new SseDecoder())->events($response->chunks()) as $event) {
                if ($control->isClosed()) { return; }
                if ($event->data === '[DONE]') { $done = true; break; }
                $data = json_decode($event->data, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($data) || isset($data['error'])) {
                    throw new ProviderException('Invalid streamed response.', kind: FailureKind::INVALID_RESPONSE, providerId: $this->getId());
                }
                $model = $data['model'] ?? $model;
                $usage = $data['usage'] ?? $usage;
                $choices = $data['choices'] ?? [];
                if (!is_array($choices) || count($choices) > 1) { throw new JsonException('Invalid streamed choice list.'); }
                foreach ($choices as $choice) {
                    if (!is_array($choice)) { throw new JsonException('Invalid streamed choice.'); }
                    if (($choice['index'] ?? 0) !== 0) { throw new JsonException('Only one streaming choice is supported.'); }
                    if ($finish !== null && !empty($choice['delta'])) { throw new JsonException('Content after finish reason.'); }
                    $delta = $choice['delta'] ?? [];
                    if (!is_array($delta) || isset($delta['function_call']) || isset($delta['audio']) || isset($delta['images']) || isset($delta['refusal'])
                        || (isset($delta['role']) && $delta['role'] !== 'assistant')) { throw new JsonException('Unsupported streamed delta.'); }
                    if (isset($delta['content'])) {
                        if (!is_string($delta['content'])) { throw new JsonException('Expected text delta.'); }
                        $text .= $delta['content'];
                        if ($delta['content'] !== '') { yield new ProviderStreamEvent('text.delta', $delta['content']); }
                        if ($control->isClosed()) { return; }
                    }
                    $fragments = $delta['tool_calls'] ?? [];
                    if (!is_array($fragments) || !array_is_list($fragments)) { throw new JsonException('Invalid tool fragment list.'); }
                    foreach ($fragments as $fragment) {
                        if (!is_array($fragment) || (isset($fragment['function']) && !is_array($fragment['function']))) { throw new JsonException('Invalid tool fragment.'); }
                        $index = $fragment['index'] ?? null;
                        if (!is_int($index) || $index < 0 || (isset($fragment['type']) && $fragment['type'] !== 'function')) { throw new JsonException('Invalid streamed tool index/type.'); }
                        $tools[$index] ??= ['id' => '', 'type' => 'function', 'function' => ['name' => '', 'arguments' => '']];
                        foreach (['id', 'name', 'arguments'] as $field) {
                            $piece = $field === 'id' ? ($fragment['id'] ?? '') : ($fragment['function'][$field] ?? '');
                            if (!is_string($piece)) { throw new JsonException('Invalid tool fragment.'); }
                            if ($field === 'id') { $tools[$index]['id'] .= $piece; } else { $tools[$index]['function'][$field] .= $piece; }
                        }
                        $additional = array_diff_key($fragment, array_flip(['index', 'id', 'type', 'function']));
                        $tools[$index] = array_replace_recursive($tools[$index], $additional);
                        yield new ProviderStreamEvent('tool.delta', $fragment['function']['arguments'] ?? '', $index);
                        if ($control->isClosed()) { return; }
                    }
                    $finish = $choice['finish_reason'] ?? $finish;
                }
            }
            if ($control->isClosed()) { return; }
            if (!$done || $finish === null) {
                throw new ProviderException('Stream ended before its terminal marker and finish reason.', kind: FailureKind::TRANSPORT, providerId: $this->getId());
            }
            ksort($tools);
            $message = ['role' => 'assistant', 'content' => $text, 'tool_calls' => array_values($tools)];
            try {
                $result = $this->parseResult(array_filter([
                    'choices' => [['message' => $message, 'finish_reason' => $finish]], 'usage' => $usage, 'model' => $model,
                ], static fn(mixed $value): bool => $value !== null), $requestId);
            } catch (RuntimeException $failure) {
                throw new ProviderException('Invalid completed stream response.', previous: $failure, kind: FailureKind::INVALID_RESPONSE, providerId: $this->getId());
            }
            yield new ProviderStreamEvent('turn.completed', result: $result);
        } catch (ProviderException $failure) {
            throw $failure;
        } catch (JsonException $failure) {
            throw new ProviderException('Malformed SSE payload.', previous: $failure, kind: FailureKind::INVALID_RESPONSE, providerId: $this->getId());
        } catch (RuntimeException $failure) {
            throw new ProviderException('Streaming transport or response failed.', previous: $failure, kind: FailureKind::TRANSPORT, providerId: $this->getId());
        } finally {
            $response?->close();
            if ($closer !== null) { $control->remove($closer); }
        }
    }
}
