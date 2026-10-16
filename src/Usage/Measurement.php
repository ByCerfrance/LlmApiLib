<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Usage;

use InvalidArgumentException;

readonly class Measurement
{
    public function __construct(public string $name, public string $unit, public int|float|null $value)
    {
        if ($name === '' || $unit === '' || ($value !== null && (!is_finite((float) $value) || $value < 0))) {
            throw new InvalidArgumentException('Measurements require names, units and non-negative finite values or null.');
        }
    }
}
