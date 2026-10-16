<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Model;

use ByCerfrance\LlmApiLib\Usage\Pricing\Pricing;
use InvalidArgumentException;
use Override;

readonly class Model implements ModelInterface
{
    public const TUNING_PARAMETERS = [
        'temperature', 'top_p', 'top_k', 'seed', 'n', 'logprobs',
        'top_logprobs', 'presence_penalty', 'frequency_penalty',
    ];

    /**
     * @param list<Capability>|null $capabilities Null is unknown; an empty list explicitly supports none.
     * @param list<string> $unsupportedParameters Unsupported root or generation-option paths.
     * @param array<string, list<string|int|float|bool>> $parameterValues
     */
    public function __construct(
        private string $name,
        private ?array $capabilities = null,
        private ?int $maxContextTokens = null,
        private ?int $maxOutputTokens = null,
        private ?bool $tunable = null,
        private array $unsupportedParameters = [],
        private array $parameterValues = [],
        private ?Pricing $pricing = null,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Model name must not be empty.');
        }
        foreach ($capabilities ?? [] as $capability) {
            if (!$capability instanceof Capability) {
                throw new InvalidArgumentException('Capabilities must contain Capability enum values.');
            }
        }
        foreach ([$maxContextTokens, $maxOutputTokens] as $limit) {
            if ($limit !== null && $limit < 1) {
                throw new InvalidArgumentException('Model token limits must be positive.');
            }
        }
        foreach ([...$unsupportedParameters, ...array_keys($parameterValues)] as $path) {
            $this->validateParameterPath($path);
        }
        foreach ($parameterValues as $values) {
            if (!is_array($values) || !array_is_list($values) || $values === []) {
                throw new InvalidArgumentException('Allowed parameter values must be non-empty lists.');
            }
            foreach ($values as $value) {
                if (!is_scalar($value) || (is_float($value) && !is_finite($value))) {
                    throw new InvalidArgumentException('Allowed parameter values must be finite scalar values.');
                }
            }
        }
    }

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[Override]
    public function getPricing(): ?Pricing
    {
        return $this->pricing;
    }

    #[Override]
    public function getCapabilities(): ?array
    {
        return $this->capabilities === null ? null : array_values($this->capabilities);
    }

    #[Override]
    public function supports(Capability $capability): ?bool
    {
        return $this->capabilities === null ? null : in_array($capability, $this->capabilities, true);
    }

    #[Override]
    public function getMaxContextTokens(): ?int
    {
        return $this->maxContextTokens;
    }

    #[Override]
    public function getMaxOutputTokens(): ?int
    {
        return $this->maxOutputTokens;
    }

    #[Override]
    public function isTunable(): ?bool
    {
        return $this->tunable;
    }

    #[Override]
    public function getUnsupportedParameters(): array
    {
        return array_values(array_unique([
            ...($this->tunable === false ? self::TUNING_PARAMETERS : []),
            ...$this->unsupportedParameters,
        ]));
    }

    #[Override]
    public function getParameterValues(): array
    {
        return $this->parameterValues;
    }

    private function validateParameterPath(string $path): void
    {
        $parts = explode('.', $path);
        if ($path === '' || in_array('', $parts, true)
            || in_array($parts[0], ['model', 'messages', 'tools', 'functions', 'stream'], true)
            || array_intersect($parts, ['schema', 'properties', 'definitions', '$defs']) !== []) {
            throw new InvalidArgumentException(sprintf('Invalid generation parameter path "%s".', $path));
        }
    }
}
