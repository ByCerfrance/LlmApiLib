<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage\Pricing;

use ByCerfrance\LlmApiLib\Usage\Measurement;
use InvalidArgumentException;

readonly class Cost
{
    public function __construct(public ?float $amount, public string $currency)
    {
        if ($currency === '' || ($amount !== null && (!is_finite($amount) || $amount < 0))) {
            throw new InvalidArgumentException('Costs require a currency and a finite non-negative amount or null.');
        }
    }

    public function toMeasurement(): Measurement
    {
        return new Measurement('cost', $this->currency, $this->amount);
    }
}
