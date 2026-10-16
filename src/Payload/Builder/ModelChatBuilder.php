<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload\Builder;

use BackedEnum;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\ModelInterface;
use ByCerfrance\LlmApiLib\Model\ParameterPolicy;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\ChatPayload;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;
use InvalidArgumentException;
use Override;
use Psr\Log\LoggerInterface;

/** @internal Validates the root payload after extensions, before protocol mappings. */
readonly class ModelChatBuilder implements BuilderInterface
{
    public function __construct(
        private ParameterPolicy $policy = ParameterPolicy::STRICT,
        private ?LoggerInterface $logger = null,
    ) {
        if ($policy === ParameterPolicy::OMIT_UNSUPPORTED && $logger === null) {
            throw new InvalidArgumentException('Omitting unsupported parameters requires a logger.');
        }
    }

    #[Override]
    public function supports(mixed $value, BuildContext $context): bool
    {
        return $value instanceof ChatPayload && $context->model instanceof ModelInterface;
    }

    #[Override]
    public function build(mixed $value, BuildContext $context): ChatPayload
    {
        if (!$value instanceof ChatPayload || !$context->model instanceof ModelInterface) {
            throw new InvalidArgumentException('Expected a root chat payload and ModelInterface.');
        }
        $model = $context->model;
        $payload = (new PayloadBuilder())->build($value->jsonSerialize());
        foreach ($model->getUnsupportedParameters() as $path) {
            $this->validatePath($payload, $path, $model->getName());
        }
        foreach ($model->getParameterValues() as $path => $values) {
            $this->validatePath($payload, $path, $model->getName(), $values);
        }

        foreach (['max_completion_tokens', 'max_tokens'] as $field) {
            $maximum = $payload[$field] ?? null;
            if ($maximum !== null && $model->getMaxOutputTokens() !== null && $maximum > $model->getMaxOutputTokens()) {
                throw new InvalidArgumentException(sprintf('Requested output exceeds the %d-token limit of model "%s".', $model->getMaxOutputTokens(), $model->getName()));
            }
        }

        $format = $payload['response_format'] ?? null;
        $required = match (is_array($format) ? ($format['type'] ?? null) : null) {
            'json_object' => Capability::JSON_OUTPUT,
            'json_schema' => Capability::JSON_SCHEMA,
            'text' => Capability::TEXT,
            default => null,
        };
        if ($required !== null && $model->supports($required) === false) {
            throw new InvalidArgumentException(sprintf('Model "%s" does not support %s.', $model->getName(), $required->value));
        }
        if (isset($payload['reasoning_effort']) && $model->supports(Capability::REASONING) === false) {
            throw new InvalidArgumentException(sprintf('Model "%s" does not support reasoning.', $model->getName()));
        }

        // Keep the marker so the next provider-specific builder still sees the root.
        return new ChatPayload($payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string|int|float|bool>|null $allowed Null means unsupported regardless of value.
     */
    private function validatePath(array &$payload, string $path, string $model, ?array $allowed = null): void
    {
        $parts = explode('.', $path);
        $key = array_pop($parts);
        $parent = &$payload;
        foreach ($parts as $part) {
            if (!isset($parent[$part]) || !is_array($parent[$part])) {
                return;
            }
            $parent = &$parent[$part];
        }
        if (!isset($parent[$key])) {
            return;
        }
        $value = $parent[$key];
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }
        if ($allowed !== null && in_array($value, $allowed, true)) {
            return;
        }
        if ($this->policy === ParameterPolicy::STRICT) {
            throw new InvalidArgumentException(sprintf('Parameter "%s" is incompatible with model "%s".', $path, $model));
        }
        unset($parent[$key]);
        $this->logger?->warning('Unsupported generation parameter omitted.', ['parameter' => $path, 'model' => $model]);
    }
}
