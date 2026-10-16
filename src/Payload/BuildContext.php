<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload;

use ByCerfrance\LlmApiLib\Model\ModelInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderInterface;

readonly class BuildContext
{
    public function __construct(
        public ?ProviderInterface $provider = null,
        public ?ModelInterface $model = null,
    ) {
    }
}
