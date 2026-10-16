<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Image\ImageRequestInterface;
use ByCerfrance\LlmApiLib\Model\ModelInterface;

readonly class ProviderImageRequest
{
    public function __construct(public ModelInterface $model, public ImageRequestInterface $request)
    {
    }
}
