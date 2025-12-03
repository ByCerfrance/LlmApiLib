<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Content;

use ByCerfrance\LlmApiLib\Model\CapabilityRequirement;
use JsonSerializable;
use Override;

interface ContentInterface extends CapabilityRequirement, JsonSerializable
{
    #[Override]
    public function jsonSerialize(bool $encapsulated = false): mixed;
}
