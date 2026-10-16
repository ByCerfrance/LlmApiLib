<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

use InvalidArgumentException;

readonly class UsageRecord
{
    /** @var list<Measurement> */
    private array $measurements;

    /** @param list<Measurement> $measurements */
    public function __construct(
        public string $id,
        array $measurements,
        public ?string $providerId = null,
        public ?string $modelName = null,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('A usage record requires an identity.');
        }
        $keys = [];
        foreach ($measurements as $measurement) {
            if (!$measurement instanceof Measurement) {
                throw new InvalidArgumentException('Expected Measurement values.');
            }
            $key = json_encode([$measurement->name, $measurement->unit], JSON_THROW_ON_ERROR);
            if (isset($keys[$key])) {
                throw new InvalidArgumentException('Duplicate metric/unit in usage record.');
            }
            $keys[$key] = true;
        }
        $this->measurements = array_values($measurements);
    }

    public static function fromTokens(string $id, TokenUsageInterface $usage, ?string $providerId = null, ?string $modelName = null): self
    {
        return new self($id, [
            new Measurement('input', 'token', $usage->getPromptTokens()),
            new Measurement('output', 'token', $usage->getCompletionTokens()),
            new Measurement('total', 'token', $usage->getTotalTokens()),
            new Measurement('cached_input', 'token', $usage->getCachedTokens()),
        ], $providerId, $modelName);
    }

    /** @return list<Measurement> */
    public function getMeasurements(): array
    {
        return $this->measurements;
    }
}
