<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\ResponseFormat;

use ByCerfrance\LlmApiLib\CapabilityRequirement;
use JsonSerializable;

interface ResponseFormatInterface extends CapabilityRequirement, JsonSerializable
{
}
