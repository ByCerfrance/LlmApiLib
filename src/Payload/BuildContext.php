<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload;

use ByCerfrance\LlmApiLib\LlmInterface;

readonly class BuildContext
{
    public function __construct(
        public ?LlmInterface $provider = null,
    ) {
    }
}
