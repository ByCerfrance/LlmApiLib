<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload;

use ByCerfrance\LlmApiLib\LlmInterface;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use ByCerfrance\LlmApiLib\Model\ModelInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderInterface;

readonly class BuildContext
{
    public function __construct(
        public LlmInterface|ProviderInterface|null $provider = null,
        public ModelInfo|ModelInterface|null $model = null,
    ) {
    }
}
