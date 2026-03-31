<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Content;

use Override;
use Stringable;

final readonly class NullContent implements ContentInterface, Stringable
{
    #[Override]
    public function __toString(): string
    {
        return '';
    }

    #[Override]
    public function jsonSerialize(bool $encapsulated = false): null
    {
        return null;
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        return [];
    }
}
