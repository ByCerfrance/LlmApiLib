<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload;

use ByCerfrance\LlmApiLib\LlmInterface;
use ByCerfrance\LlmApiLib\Model\ModelInfo;

readonly class BuildContext
{
    public function __construct(
        public ?LlmInterface $provider = null,
        public ?ModelInfo $model = null,
    ) {
    }
}
