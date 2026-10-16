<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

use InvalidArgumentException;

readonly class UnitPricing
{
    public function __construct(public string $unit, public string $currency, public ?float $rate, public float $perUnits = 1)
    {
        if ($unit === '' || $currency === '' || !is_finite($perUnits) || $perUnits <= 0
            || ($rate !== null && (!is_finite($rate) || $rate < 0))) {
            throw new InvalidArgumentException('Invalid unit price.');
        }
    }

    public function estimate(Measurement $measurement): Cost
    {
        if ($measurement->unit !== $this->unit) {
            throw new InvalidArgumentException('Measurement and price units must match.');
        }

        return new Cost($measurement->value === 0 || $measurement->value === 0.0 ? 0.0
            : ($measurement->value === null || $this->rate === null ? null : $measurement->value * $this->rate / $this->perUnits), $this->currency);
    }
}
