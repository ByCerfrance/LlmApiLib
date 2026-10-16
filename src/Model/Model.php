<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Model;

use InvalidArgumentException;
use Override;

readonly class Model implements ModelInterface
{
    public function __construct(private string $name)
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Model name must not be empty.');
        }
    }

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }
}
