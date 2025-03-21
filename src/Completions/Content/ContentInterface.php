<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completions\Content;

use JsonSerializable;
use Override;

interface ContentInterface extends JsonSerializable
{
    #[Override]
    public function jsonSerialize(bool $encapsulated = false): mixed;
}
